<?php
##########################################################
# convierte de marcxml a EprintsXML
##########################################################

class MarcToEprintsConverter
{
    private $marc_ns = "http://www.loc.gov/MARC21/slim";

    public function convertFile($inputFile, $outputFile)
    {
        try {
            // Cargar el archivo MARCXML
            $marcxml = simplexml_load_file($inputFile);
            if ($marcxml === false) {
                throw new Exception("No se pudo cargar el archivo de entrada");
            }

            // Registrar el namespace
            $marcxml->registerXPathNamespace('marc', $this->marc_ns);

            // Crear el documento ePrints
            $eprintsXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eprints></eprints>');


            // Procesar cada registro
            $records = $marcxml->xpath('//marc:record');
            foreach ($records as $record) {
                $eprint = $eprintsXml->addChild('eprint');
                $this->processRecord($record, $eprint);
            }

            // Usar DOM para formatear
            $dom = dom_import_simplexml($eprintsXml)->ownerDocument;
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = true;

            if ($dom->save($outputFile) === false) {
                throw new Exception("No se pudo guardar el archivo de salida");
            }

            // Guardar el resultado
            $result = $eprintsXml->asXML($outputFile);
            if ($result === false) {
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
        // Procesar campos MARC
        foreach ($record->xpath('.//marc:datafield') as $datafield) {
            $tag = (string) $datafield['tag'];

            switch ($tag) {
                case '245': // Título
                    $this->processTitle($datafield, $eprint);
                    break;
                case '100': // Autor
                    $this->processAuthor($datafield, $eprint);
                    break;
                case '264': // Publicación
                    $this->processPublication($datafield, $eprint);
                    break;
                case '500': // Notas
                    $this->processNotes($datafield, $eprint);
                    break;    
                case '520': // Resumen
                    $this->processAbstract($datafield, $eprint);
                    break;
                case '653': // Temas
                    $this->processSubjects($datafield, $eprint);
                    break;
            }
        }
    }

    private function processTitle($datafield, &$eprint)
    {
        $title = $datafield->xpath(".//marc:subfield[@code='a']");
        $title_b = $datafield->xpath(".//marc:subfield[@code='b']");
        $title_c = $datafield->xpath(".//marc:subfield[@code='c']");
        if ($title && (isset($title[0]) || isset($title_b[0]))) {

            $eprint->addChild('title', htmlspecialchars(trim((string) $title[0])) ." ". htmlspecialchars(trim((string) $title_b[0])));
        }
    }

    private function processAuthor($datafield, &$eprint)
    {
        $author = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($author && isset($author[0])) {
            $creators = $eprint->addChild('creators');
            $item = $creators->addChild('item');
            $name = $item->addChild('name');

            // Dividir nombre (simplificación)
            $authorName = explode(',', trim((string) $author[0]));
            $name->addChild('family', htmlspecialchars(trim($authorName[0]))) . "\n";
            if (isset($authorName[1])) {
                $name->addChild('given', htmlspecialchars(trim($authorName[1]))) . "\n";
            }
        }
    }

    private function processPublication($datafield, &$eprint)
    {
        $publisher = $datafield->xpath(".//marc:subfield[@code='b']");
        $date = $datafield->xpath(".//marc:subfield[@code='c']");

        if ($publisher && isset($publisher[0])) {
            $eprint->addChild('publisher', htmlspecialchars(trim((string) $publisher[0])));
        }
        if ($date && isset($date[0])) {
            $eprint->addChild('date', htmlspecialchars(trim((string) $date[0])));
        }
    }

    private function processAbstract($datafield, &$eprint)
    {
        $abstract = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($abstract && isset($abstract[0])) {
            $eprint->addChild('abstract', htmlspecialchars(trim((string) $abstract[0])));
        }
    }

    private function processSubjects($datafield, &$eprint)
    {
        // Buscar el elemento <subjects> existente, si no existe lo creamos
        if (!isset($eprint->subjects)) {
            $subjects = $eprint->addChild('subjects');
        } else {
            $subjects = $eprint->subjects;
        }

        // Obtener el tema del subcampo 'a'
        $subject = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($subject && isset($subject[0])) {
            // Agregar cada tema como un <item> dentro de <subjects>
            $subjects->addChild('item', htmlspecialchars(trim((string) $subject[0])));
        }
    }

    private function processNotes($datafield, &$eprint)
    {
        // Buscar el elemento <Notes> existente, si no existe lo creamos
        if (!isset($eprint->notes)) {
            $notes = $eprint->addChild('notes');
        } else {
            $notes = $eprint->notes;
        }

        // Obtener el tema del subcampo 'a'
        $note = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($note && isset($note[0])) {
            // Agregar cada tema como un <item> dentro de <subjects>
            $notes->addChild('item', htmlspecialchars(trim((string) $note[0])));
        }
    }
}

// Ejemplo de uso
function main()
{
    $converter = new MarcToEprintsConverter();

    $inputFile = "tesis.xml";
    $outputFile = "output_eprints.xml";

    echo "Convirtiendo $inputFile a $outputFile...\n";
    $success = $converter->convertFile($inputFile, $outputFile);

    if ($success) {
        echo "Conversión completada exitosamente\n";
    } else {
        echo "Falló la conversión\n";
    }
}

// Ejecutar
main();

?>