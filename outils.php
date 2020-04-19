<?php
/**
 * @Author: G² <info@formatux.be> aka @maitrepylos
 * Date: 19/04/20
 * Description :
 * Fonctions permettant de répondre au défis de @FredBouchery
 * Ceci à été fait à l'arrach...comme au boulot (pour aller vite) :)
 * Ce n'est pas réutilisable et cela ne fait que le défis.
 * Librement inspiré de l'exemple : https://bastienmalahieude.fr/importer-un-fichier-csv-en-tableau-php/
 */


/**
 * @param $fichier
 * @param $options
 * @param string $enclosure
 * @return false|string
 * @throws Exception
 */
function importeCsvToTableau($fichier, $options, $enclosure = '"'): ?string
{


    $csv_string = file_get_contents($fichier);
    if ((bool)$csv_string === false) {
        throw new Exception(message('Le fichier csv est vide'));
    }

    $delimiter = rechercheSeparateur($csv_string);
    if ($delimiter === false) {
        throw new Exception(message("le délimiteur n'est pas existant"));
    }


    $lines = explode("\n", $csv_string);

    $head = str_getcsv(array_shift($lines), $delimiter, $enclosure);

    $count_head = count($head);

    if ($options['fields'] !== false && (count($options['fields']) === $count_head)) {
        $head = $options['fields'];
    }

    $array = [];

    foreach ($lines as $line) {

        //Si nous trouvons une ligne vide
        if (empty($line)) {
            continue;
        }


        $csv = str_getcsv($line, $delimiter, $enclosure);

        if (count($csv) === $count_head) {
            $array[] = array_combine($head, $csv);
        } else {
            echo message("Une ligne du fichier csv ne correpsond pas aux nombres de valeurs demandé à l'entête");
            exit();
        }

    }

    if ($options['desc'] !== false) {

        $array = adapteType($array, $options['desc']);

    }

    if ($options['aggregate'] !== false) {

        $array = groupBy($array, $options['aggregate']);
    }

    if ($options['pretty']) {
        return json_encode($array, JSON_PRETTY_PRINT);
    }
    return json_encode($array);
}


/**
 * @param $message
 * @param bool $bool
 * @return mixed
 */
function message($message, $bool = false): string
{
    return ` echo "\033[0m $message \033[31m❌ "   `;

}


/**
 * @param $fichier
 * @return bool|false|int|string
 */
function rechercheSeparateur($fichier): ?string
{

    //définit les séparateurs les plus courant
    $separateurs = [';' => 0, ',' => 0, "\t" => 0, '|' => 0, ':' => 0];

    // Pour chaque délimiteur, nous comptons le nombre de fois qu'il peut être trouvé dans la chaîne csv
    foreach ($separateurs as $separateur => &$count) {
        $count = substr_count($fichier, $separateur);
    }

    // recherche le séparateur compter le plus souvent et le définir
    $resultat = array_search(max($separateurs), $separateurs);
    if ($separateurs[$resultat] === 0) {
        return false;
    }
    return $resultat;

}


/**
 * Parseur des options
 * @param $arguments
 * @return array
 */
function options($arguments): array
{

    $tableau = [
        'pretty' => false,
        'fields' => false,
        'aggregate' => false,
        'desc' => false
    ];

    if (in_array('--pretty', $arguments)) {
        $tableau['pretty'] = true;
    }

    if (in_array('--fields', $arguments)) {
        $indice = array_search('--fields', $arguments) + 1;
        if ($arguments[$indice][0] !== '-' || $arguments[$indice][1] !== '-') {
            $delimiter = rechercheSeparateur($arguments[$indice]);
            $tableau['fields'] = explode($delimiter, $arguments[$indice]);
        }
    }

    if (in_array('--aggregate', $arguments)) {
        $indice = array_search('--aggregate', $arguments) + 1;
        if ($arguments[$indice][0] !== '-' || $arguments[$indice][1] !== '-') {
            $tableau['aggregate'] = $arguments[$indice];
        }
    }
    if (in_array('--desc', $arguments)) {
        $indice = array_search('--desc', $arguments) + 1;
        if ($arguments[$indice][0] !== '-' || $arguments[$indice][1] !== '-') {
            $tableau['desc'] = description($arguments[$indice]);
        }
    }

    return $tableau;
}

/**
 * Parseur du fichier de description
 * @param $fichier
 * @return array|bool
 */
function description($fichier): array
{

    $fichier = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ((bool)$fichier === false) {
        return false;
    }
    $tableau = [];

    foreach ($fichier as $ligne) {

        if ($ligne[0] === "#") {
            continue;
        }
        [$name, $type] = explode('=', $ligne);
        if (trim($type)[0] === '?') {
            $re = '/\?(.*)/m';

            preg_match($re, (string)$type, $matches);
            $tableau[trim($name)][] = null;
            $tableau[trim($name)][] = $matches[1];
            continue;
        }
        $tableau[trim($name)][] = true;
        $tableau[trim($name)][] = trim($type);

    }

    return $tableau;


}

/**
 * @param $tableau
 * @param $name
 * @return array
 * Librement inspiré de https://stackoverflow.com/questions/982344/grouping-arrays-in-php
 */
function groupBy($tableau, $name): array
{
    $groups = [];
    foreach ($tableau as $item) {
        $key = $item[$name];
        if (!isset($groups[$key])) {
            unset($item[$name]);
            $groups[$key] = [
                [$item]
            ];
        } else {
            unset($item[$name]);
            $groups[$key][][] = $item;
        }
    }
    return $groups;
}

/**
 * @param $tableau
 * @param $options
 * @return array
 * @throws Exception
 */
function adapteType($tableau, $options): array
{

    $date = ["date", "datetime", "time"];

    foreach ($tableau as &$val) {
        foreach ($val as $key => &$type) {
            if (array_key_exists($key, $options)) {

                if ((bool)$type === false && $options[$key][0] === null) {
                    $type = null;
                    continue;
                }
                if ((bool)$type === false && $options[$key][0] === true) {
                    throw new Exception(message('Element vide sans précision', false));
                }

                if (in_array($key, $date)) {
                    $type = adapteDate($type, $options[$key][1]);
                    continue;
                }

                settype($type, $options[$key][1]);
                continue;
            }
        }

    }
    return $tableau;
}

/**
 * @param $type
 * @param $key
 * @return bool|string
 */
function adapteDate($type, $key): string
{

    ini_set('date.timezone', 'Europe/Brussels');

    $format = ["date" => "Y-m-d", "datetime" => "Y-m-d h:m:s", "time" => "h:m:s"];
    try {
        $date = new DateTime($type);
        return $date->format($format[$key]);

    } catch (Exception $e) {
        return 'no';
    }

}

