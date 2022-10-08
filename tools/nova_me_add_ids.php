<?php
if (PHP_SAPI != 'cli') die('cli only');         // php-cli only

/*******************************************************************

   Description
   - update OpenEdition editorial model to add Nova Specific fields
   Install
   - copy or make a symbolic link of the file in the root directory
     of the Lodel install
   Execute
   - cd PATH_TO_ROOT_LODEL_DIRECTORY
   - php nova_me_update.php mysite # update the site "mysite"
     or
     php nova_me_upgrade.php all # update all sites (excepted site listed in the array $exclude. See below)
   - after execution, file should be remove from lodel root directory
 *******************************************************************/

require_once('lodel/install/scripts/me_manipulation_func.php');
define('DO_NOT_DIE', true);                      // only die of a server error
// define('QUIET', true);                        // no output
$exclude = array();                              // the $exclude array may contain site names to be excluded from processing at execution with the  parameter "all"

$sites = new ME_sites_iterator($argv, 'errors'); // 'errors' display only errors ot the function ->m()
while ($siteName = $sites->fetch()) {
	if (in_array($siteName,$exclude)) continue;

    print "Mise à jour du ME: ajout des champs idref, orcid, ark, hal, isni comme champ de relations entre les entités et la classe auteurs:\n";

    /*
     * Création des champs idref, orcid, ark, hal, isni
    */

    $fields = [
        'idref' => ['title'=>'IDREF', 'cond'=>'*', 'gui_user_complexity'=>'16', 'edition'=>'editable', 'status'=>'1', 'otx' => "//tei:idno[@type='IDREF']"],
        'orcid' => ['title'=>'ORCID', 'cond'=>'*', 'gui_user_complexity'=>'16', 'edition'=>'editable', 'status'=>'1', 'otx' => "//tei:idno[@type='ORCID']"],
        'ark'   => ['title'=>'ARK',   'cond'=>'*', 'gui_user_complexity'=>'16', 'edition'=>'editable', 'status'=>'1', 'otx' => "//tei:idno[@type='ARK']"],
        'hal'   => ['title'=>'HAL',   'cond'=>'*', 'gui_user_complexity'=>'16', 'edition'=>'editable', 'status'=>'1', 'otx' => "//tei:idno[@type='HAL']"],
        'isni'  => ['title'=>'ISNI',  'cond'=>'*', 'gui_user_complexity'=>'16', 'edition'=>'editable', 'status'=>'1', 'otx' => "//tei:idno[@type='ISNI']"],
    ];

    // vérification de l'exitence de la classe
    $auteurs = Cl::get('auteurs');
    if (!$auteurs->error) {

        foreach ($fields as $name => $infos) {

            // test si le champ eiste déjà
            $tf = TF::get('entities_auteurs', $name);
            if (!$tf->error) {
                print "le champ « $name » existe déjà.\n";
            } else {

                // me_manipulation_func ne prend pas en charge les relations entre entités et personnes
                // utilisation de la logic 'tablefields' directement
                $infos['name'] = $name;
                $infos['class'] = 'entities_auteurs';
                $infos['type'] = 'tinytext';
                $infos['idgroup'] = 0;
                $infos['id'] = 0;

                // création
                $Lo = Logic::getLogic('tablefields');
                $Lo->editAction($infos, $error);

                if ($error) {
                    print "Erreur lors de la création du champ « $name »\n";
                    print " " . join(", ", $error) . "\n";
                } else {
                    print "Champ « $name » créé\n";
                }
            }
        }
    } else {
        print "Erreur avec la classe auteurs, Les champs idref,… ne seront pas créé.\n";
        print " " . join(", ", $auteurs->errors) . "\n";
    }

}
