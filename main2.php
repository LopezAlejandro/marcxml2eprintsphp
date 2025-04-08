<?php
class MarcToEprintsConverter
{
    private $marc_ns = "http://www.loc.gov/MARC21/slim";

    public function convertFile($inputFile, $outputFile)
    {
        try {
            $marcxml = simplexml_load_file($inputFile);
            if ($marcxml === false) {
                throw new Exception("No se pudo cargar el archivo de entrada");
            }

            $marcxml->registerXPathNamespace('marc', $this->marc_ns);
            $eprintsXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eprints></eprints>');

            $records = $marcxml->xpath('//marc:record');
            echo "Número de registros encontrados: " . count($records) . "\n";

            foreach ($records as $record) {
                $eprint = $eprintsXml->addChild('eprint');
                $this->processRecord($record, $eprint);
            }

            $dom = dom_import_simplexml($eprintsXml)->ownerDocument;
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = true;

            if ($dom->save($outputFile) === false) {
                throw new Exception("No se pudo guardar el archivo de salida");
            }
            return true;

        } catch (Exception $e) {
            echo "Error durante la conversión: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function processRecord($record, &$eprint)
    {
        $notes = [];
        $keywords = [];
        $abstractc = [];

        foreach ($record->xpath('.//marc:datafield') as $datafield) {
            $tag = (string) $datafield['tag'];
            echo "Procesando tag: $tag\n";

            switch ($tag) {
                case '245':
                    $this->processTitle($datafield, $eprint);
                    break;
                case '100':
                    $this->processAuthor($datafield, $eprint);
                    break;
                case '264':
                    $this->processPublication($datafield, $eprint);
                    break;
                case '500':
                    $this->collectNote($datafield, $notes);
                    break;
                case '260':
                    $this->processFacultad($datafield, $eprint);
                    break;
                case '520':
                    $this->collectAbstract($datafield, $abstractc);
                    break;
                case '690':
                    $this->collectKeywords($datafield, $keywords);
                    break;
            }
        }

        if (!empty($notes)) {
            $eprint->addChild('note', htmlspecialchars(implode(', ', $notes)));
        }
        if (!empty($keywords)) {
            $eprint->addChild('keywords', htmlspecialchars(implode(', ', $keywords)));
        }
        if (!empty($abstractc)) {
            $eprint->addChild('abstract', htmlspecialchars(implode(', ', $abstractc)));
        }
    }

    private function processAuthor($datafield, &$eprint)
    {
        $name = '';
        $role = '';
        
        echo "Entrando en processAuthor\n";
        foreach ($datafield->subfield as $subfield) {
            $code = (string)$subfield['code'];
            $value = trim((string)$subfield);
            echo "Subfield code=$code, value=$value\n";
            
            if ($code === 'a') {
                $name = $value;
            }
            if ($code === 'e') {
                $role = $value;
            }
        }

        if (empty($name)) {
            echo "No se encontró nombre, saliendo\n";
            return;
        }

        echo "Procesando: name=$name, role=$role\n";
        $nameParts = explode(',', $name, 2);
        $family = trim($nameParts[0]);
        $given = isset($nameParts[1]) ? trim($nameParts[1]) : '';

        if ($role === 'author') {
            if (!isset($eprint->creators)) {
                $eprint->addChild('creators');
                echo "Creado nodo creators\n";
            }
            $item = $eprint->creators->addChild('item');
            $nameElement = $item->addChild('name');
            $nameElement->addChild('family', htmlspecialchars($family));
            $nameElement->addChild('given', htmlspecialchars($given));
            echo "Añadido autor: $family, $given\n";
        } else {
            if (!isset($eprint->contributors)) {
                $eprint->addChild('contributors');
                echo "Creado nodo contributors\n";
            }
            $item = $eprint->contributors->addChild('item');
            $nameElement = $item->addChild('name');
            $nameElement->addChild('family', htmlspecialchars($family));
            $nameElement->addChild('given', htmlspecialchars($given));
            $item->addChild('type', 'contributor');
            echo "Añadido contribuidor: $family, $given\n";
        }
    }

    private function processTitle($datafield, &$eprint) { /* ... sin cambios ... */ }
    private function collectNote($datafield, &$notes) { /* ... sin cambios ... */ }
    private function collectKeywords($datafield, &$keywords) { /* ... sin cambios ... */ }
    private function processPublication($datafield, &$eprint) { /* ... sin cambios ... */ }
    private function processFacultad($datafield, &$eprint) { /* ... sin cambios ... */ }
    private function collectAbstract($datafield, &$abstractc) { /* ... sin cambios ... */ }
}

function main()
{
    $converter = new MarcToEprintsConverter();
    $inputFile = "tesis-maef.xml";
    $outputFile = "eprints.xml";

    echo "Convirtiendo $inputFile a $outputFile...\n";
    $success = $converter->convertFile($inputFile, $outputFile);

    if ($success) {
        echo "Conversión completada exitosamente\n";
    } else {
        echo "Falló la conversión\n";
    }
}

main();
?>