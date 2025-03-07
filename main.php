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
            return true;

        } catch (Exception $e) {
            echo "Error durante la conversión: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function processRecord($record, &$eprint)
    {
        // Array para recolectar notas
        $notes = [];
        $keywords = [];

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
                case '500':
                    $this->collectNote($datafield, $notes); // Recolectar notas
                    break;
                case '502': // Institucion
                    $this->processFacultad($datafield, $eprint);
                    break;        
                case '520': // Resumen
                    $this->processAbstract($datafield, $eprint);
                    break;
                case '653': // Palabras Clave
                    $this->collectKeywords($datafield, $keywords);
                    break;
            }
        }
        // Agregar todas las notas combinadas como un solo <note>
        if (!empty($notes)) {
            $combinedNote = implode(', ', $notes);
            $eprint->addChild('note', htmlspecialchars($combinedNote));
        }

        // Agregar todos las palabras clave combinadas como un solo <keywords>
        if (!empty($keywords)) {
            $combinedKeyword = implode(', ', $keywords);
            $eprint->addChild('keywords', htmlspecialchars($combinedKeyword));
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

    private function collectNote($datafield, &$notes)
    {
        $note = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($note && isset($note[0])) {
            $notes[] = trim((string) $note[0]);
        }
    }

    private function collectKeywords($datafield, &$keywords)
    {
        $keyword = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($keyword && isset($keyword[0])) {
            $keywords[] = trim((string) $keyword[0]);
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

    private function processFacultad($datafield, &$eprint)
    {
        $facultad = $datafield->xpath(".//marc:subfield[@code='c']");
        
        if ($facultad && isset($facultad[0])) {
            $eprint->addChild('department', htmlspecialchars(trim((string) $facultad[0])));
        }
        $tesist = $datafield->xpath(".//marc:subfield[@code='a']");

        if ($tesist && isset($tesist[0])) {
            if (str_contains($tesist[0], 'Doctorado')) {
                $eprint->addChild('thesis_type', 'doctoral');
            } else {
                $eprint->addChild('thesis_type', htmlspecialchars(trim((string) $tesist[0])));    
            }
        }
    }

    private function processAbstract($datafield, &$eprint)
    {
        $abstract = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($abstract && isset($abstract[0])) {
            $eprint->addChild('abstract', htmlspecialchars(trim((string) $abstract[0])));
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