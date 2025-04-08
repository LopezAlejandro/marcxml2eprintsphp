<?php
##########################################################
# Convierte de MARCXML a EprintsXML usando DOMDocument
##########################################################

class MarcToEprintsConverter
{
    private $marc_ns = "http://www.loc.gov/MARC21/slim";

    public function convertFile($inputFile, $outputFile)
    {
        try {
            // Cargar el archivo MARCXML con SimpleXML para usar XPath
            $marcxml = simplexml_load_file($inputFile);
            if ($marcxml === false) {
                throw new Exception("No se pudo cargar el archivo de entrada");
            }

            // Registrar el namespace MARC
            $marcxml->registerXPathNamespace('marc', $this->marc_ns);

            // Crear el documento EPrints con DOMDocument
            $eprintsXml = new DOMDocument('1.0', 'UTF-8');
            $eprintsXml->formatOutput = true;
            $eprints = $eprintsXml->createElement('eprints');
            $eprintsXml->appendChild($eprints);

            // Procesar cada registro
            $records = $marcxml->xpath('//marc:record');
            foreach ($records as $record) {
                $eprint = $eprintsXml->createElement('eprint');
                $eprints->appendChild($eprint);
                $this->processRecord($record, $eprint, $eprintsXml);
            }

            // Guardar el archivo
            if ($eprintsXml->save($outputFile) === false) {
                throw new Exception("No se pudo guardar el archivo de salida");
            }
            return true;

        } catch (Exception $e) {
            echo "Error durante la conversión: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function processRecord($record, $eprint, $doc)
    {
        $notes = [];
        $keywords = [];
        $abstractc = [];

        // Procesar campos MARC
        foreach ($record->xpath('.//marc:datafield') as $datafield) {
            $tag = (string) $datafield['tag'];

            switch ($tag) {
                case '245': // Título
                    $this->processTitle($datafield, $eprint, $doc);
                    break;
                case '100': // Autor y Contribuidores
                    $this->processAuthor($datafield, $eprint, $doc);
                    break;
                case '264': // Publicación
                    $this->processPublication($datafield, $eprint, $doc);
                    break;
                case '260': // Institución/Facultad
                    $this->processFacultad($datafield, $eprint, $doc);
                    break;
                // case '500': //Url Oficial
                //     $this->processUrl($datafield, $eprint, $doc);   
                //     break; 
                case '520': // Resumen
                    $this->collectAbstract($datafield, $abstractc);
                    break;
                case '690': // Palabras Clave
                    $this->collectKeywords($datafield, $keywords);
                    break;
            }
        }

        // Agregar notas combinadas
        if (!empty($notes)) {
            $note = $doc->createElement('note', htmlspecialchars(implode(', ', $notes)));
            $eprint->appendChild($note);
        }

        // Agregar palabras clave combinadas
        if (!empty($keywords)) {
            $keyword = $doc->createElement('keywords', htmlspecialchars(implode(', ', $keywords)));
            $eprint->appendChild($keyword);
        }

        // Agregar resumen combinado
        if (!empty($abstractc)) {
            $abstract = $doc->createElement('abstract', htmlspecialchars(implode(', ', $abstractc)));
            $eprint->appendChild($abstract);
        }
        $type = $doc->createElement('type','thesis');
        $eprint->appendChild($type);

        $type_t = $doc->createElement('thesis_type', 'maestria');
        $eprint->appendChild($type_t);
    }

    private function processTitle($datafield, $eprint, $doc)
    {
        $title_a = $datafield->xpath(".//marc:subfield[@code='a']");
        $title_b = $datafield->xpath(".//marc:subfield[@code='b']");
        
        if ($title_a && isset($title_a[0])) {
            $titleText = trim((string)$title_a[0]);
            if ($title_b && isset($title_b[0])) {
                $titleText .= " " . trim((string)$title_b[0]);
            }
            $title = $doc->createElement('title', htmlspecialchars($titleText));
            $eprint->appendChild($title);
        }
    }

    private function processUrl($datafield, $eprint, $doc)
    {
        $url_a = $datafield->xpath(".//marc:subfield[@code='a']");

        if ($url_a && isset($url_a[0])) {
            $urlText = trim((string) $url_a[0]);
        }
        $url = $doc->createElement('official_url',htmlspecialchars($urlText));
        $eprint->appendChild($url);
    } 

    private function collectNote($datafield, &$notes)
    {
        $note = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($note && isset($note[0])) {
            $notes[] = trim((string)$note[0]);
        }
    }

    private function collectKeywords($datafield, &$keywords)
    {
        $keyword = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($keyword && isset($keyword[0])) {
            $keywords[] = trim((string)$keyword[0]);
        }
    }

    private function processAuthor($datafield, $eprint, $doc)
    {
        // Usar XPath para obtener subcampos con el namespace correcto
        $nameSubfield = $datafield->xpath(".//marc:subfield[@code='a']");
        $roleSubfield = $datafield->xpath(".//marc:subfield[@code='e']");

        $name = $nameSubfield && isset($nameSubfield[0]) ? trim((string)$nameSubfield[0]) : '';
        $role = $roleSubfield && isset($roleSubfield[0]) ? trim((string)$roleSubfield[0]) : '';

        // Depuración
        echo "Procesando tag 100: name='$name', role='$role'\n";

        if (empty($name)) {
            return; // No procesar si no hay nombre
        }

        // Separar nombre y apellido
        $nameParts = explode(',', $name, 2);
        $family = trim($nameParts[0]);
        $given = isset($nameParts[1]) ? trim($nameParts[1]) : '';

        // Crear elementos comunes
        $item = $doc->createElement('item');
        $nameElement = $doc->createElement('name');
        $nameElement->appendChild($doc->createElement('family', htmlspecialchars($family)));
        $nameElement->appendChild($doc->createElement('given', htmlspecialchars($given)));
        $item->appendChild($nameElement);

        // Determinar si es creator o contributor
        if ($role === 'author') {
            $creators = $eprint->getElementsByTagName('creators')->item(0);
            if (!$creators) {
                $creators = $doc->createElement('creators');
                $eprint->appendChild($creators);
            }
            $creators->appendChild($item);
        } else {
            $contributors = $eprint->getElementsByTagName('contributors')->item(0);
            if (!$contributors) {
                $contributors = $doc->createElement('contributors');
                $eprint->appendChild($contributors);
            }
            $item->appendChild($doc->createElement('type', 'http://www.loc.gov/loc.terms/relators/CTB'));
            $contributors->appendChild($item);
        }
    }

    private function processPublication($datafield, $eprint, $doc)
    {
        $publisher = $datafield->xpath(".//marc:subfield[@code='b']");
        $date = $datafield->xpath(".//marc:subfield[@code='c']");

        if ($publisher && isset($publisher[0])) {
            $pub = $doc->createElement('publisher', htmlspecialchars(trim((string)$publisher[0])));
            $eprint->appendChild($pub);
        }
        if ($date && isset($date[0])) {
            $dt = $doc->createElement('date', htmlspecialchars(trim((string)$date[0])));
            $eprint->appendChild($dt);
        }
    }

    private function processFacultad($datafield, $eprint, $doc)
    {
        $facultad = $datafield->xpath(".//marc:subfield[@code='b']");
        if ($facultad && isset($facultad[0])) {
            $univ = explode('.',$facultad[0]);
            $dept = $doc->createElement('department', htmlspecialchars(trim((string)$univ[1])));
            $eprint->appendChild($dept);
            $insti = $doc->createElement('institution', htmlspecialchars(trim((string) $univ[0])));
            $eprint->appendChild($insti);
        }

        $tesist = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($tesist && isset($tesist[0])) {
            $thesisType = str_contains((string)$tesist[0], 'Doctorado') ? 'doctoral' : htmlspecialchars(trim((string)$tesist[0]));
            $thesis = $doc->createElement('thesis_type', $thesisType);
            $eprint->appendChild($thesis);
        }
        
        $date = $datafield->xpath(".//marc:subfield[@code='c']");
        if ($date && isset($date[0])) {
            $dt = $doc->createElement('date', htmlspecialchars(trim((string)$date[0])));
            $eprint->appendChild($dt);
        }
    }

    private function collectAbstract($datafield, &$abstractc)
    {
        $abstract = $datafield->xpath(".//marc:subfield[@code='a']");
        if ($abstract && isset($abstract[0])) {
            $abstractc[] = trim((string)$abstract[0]);
        }
    }
}

// Ejemplo de uso
function main()
{
    $converter = new MarcToEprintsConverter();
    $inputFile = "tesis-maef.xml";
    $outputFile = "out-".$inputFile;

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