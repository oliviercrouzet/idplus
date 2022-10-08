<?php
/* Script à lancer à la racine de l'installation */

require_once('lodel/install/scripts/me_manipulation_func.php');
define('DO_NOT_DIE', true); // Ne mourir qu'en cas d'erreur grave
//  define('QUIET', true); // Pas de sortie du tout

// Pour élargir la supression aux sites non publiés indiquer -1 comme statut du site (3ème paramètre)
$sites = new ME_sites_iterator($argv, 'errors', 0); // 'errors' ne montre que les erreurs de la fonction ->m(), 0 est le statut minimal du site

while ($siteName = $sites->fetch()) {
    echo "\tsupression du plugin Idplus pour ce site \n";
    $db->execute(lq ("DELETE FROM #_TP_plugins where name='idplus';"));
}

echo "***Travail dans l'Administration générale ***\n";
echo "\tsupression du plugin Idplus dans la table 'mainplugins' \n";
$base_lodel = c::Get('database','cfg');
echo $base_lodel;
$GLOBALS['currentdb'] = $base_lodel;
usecurrentdb();
$db->execute(lq ("DELETE FROM #_TP_mainplugins where name='idplus';"));

?>
