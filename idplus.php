<?php
// ma classe doit absolument étendre la classe de base 'Plugins'
class IdPlus extends Plugins
{
    // pas besoin d'initialiser quoique ce soit à l'activation/désactivation du plugin
    // il faut toutefois les déclarer pour respecter la cohérence avec la classe parente
    public function enableAction(&$context, &$error) {}
    public function disableAction(&$context, &$error) {}

    public function postview(&$context)
    {
        
	    //if (isset($context['persons']) && $context['lodeluser']['adminlodel'] == '1') {
        if ($context['view']['tpl'] == 'edit_entities_edition' && isset($context['persons']) && $context['lodeluser']['adminlodel'] == '1') {
			$persons = $context['persons'];
			$site = $context['site'];
			$sitefile = '../../.././share/plugins/custom/idplus/data/'.$site.'.json';
			if (! file_exists($sitefile)) { 
				touch($sitefile);
			}
		    $json_data = file_get_contents($sitefile);
		    $refs =  json_decode($json_data, true);
            $authform='<div class="advancedFunc">
			           <h4>Identifiants auteurs</h4>
			           <table class="translations" style="width:90%" cellspacing="0" cellpadding="5" border="0">
		 	           <form id="idplus" action="index.php/?do=_idplus_record" method="POST">';
			$iddocument=$context['iddocument'];
			foreach ($persons as $person => $aut) {
			    foreach ($aut as $p) { 
					$prenom = $p['data']['prenom'];
					$nom = $p['data']['nomfamille'];
					$idperson = $p['data']['idperson'];
					$idref = '';
					$orcid = '';
					$idreflink='';
					$orcidlink='';
                    if (isset($refs[$idperson])) { 
                        $idref = $refs[$idperson]['idref'] ?: '';  
                        $orcid =  $refs[$idperson]['orcid'] ?: '';  
					}
					$numFound_idref=0;
					$numFound_orcid=0;
					if (!$idref) {
						//libxml_use_internal_errors(true);
					    $xml = simplexml_load_file("https://www.idref.fr/Sru/Solr?q=persname_t:(".urlencode($nom." AND ".$prenom).")&fl=ppn_z");
						/*
						if ($xml === false) {
						    //echo "Failed loading XML\n";
							foreach(libxml_get_errors() as $error) {
								//echo "\t", $error->message;
								$load_idref_error .= $error->message;
                                file_put_contents('/var/www/prairial/share/plugins/custom/idplus/octest',"ERR ".$error->message,FILE_APPEND);

							}
						}
						*/
					    $numFound_idref = (int) $xml->result['numFound'];
					    if ($numFound_idref > 0) {
						    $matches = [];
					        foreach ($xml->xpath('//str[@name="ppn_z"]') as $name) {
					            array_push($matches,'https://www.idref.fr/'.$name);
							}
							$idreflink = 'href="'.array_shift($matches).'" target="_blank" ';
							if (count($matches)) {
								$idreflink.= 'onclick="';
							    foreach ($matches as $match) {
								   $idreflink.= 'window.open(&quot;'.$match.'&quot;);';
								}
								$idreflink.='"';
							}
							    
						}
				    }
					if (!$orcid) {
					    $xml = simplexml_load_file('https://pub.orcid.org/v2.1/search?q=family-name:'.urlencode($nom).'+AND+given-names:'.urlencode($prenom));
                        $found = $xml->xpath('//search:search')[0]; 
						$numFound_orcid = (int) $found['num-found'];
					    if ($numFound_orcid > 0 ) {
						    $matches = [];
						    foreach ($xml->xpath('//search:result') as $r) {
					            array_push($matches,$r->xpath('common:orcid-identifier/common:uri')[0]);
								if (count($matches) == 5) break;
							}
							$orcidlink = 'href="'.array_shift($matches).'" target="_blank" ';
							if (count($matches)) {
								$orcidlink.= 'onclick="';
							    foreach ($matches as $match) {
								   $orcidlink.= 'window.open(&quot;'.$match.'&quot;);';
								}
								$orcidlink.='"';
							}
						}
					}

					$authform.= '<tr><td colspan="2" style="font-size:1.1em;color:#8a8a8a"><strong>'.$prenom.' '.$nom.'</strong>
							<input type="hidden" name="idperson[]" value="'.$idperson.'" />
							<input type="hidden" name="prenom[]" value="'.$prenom.'" />
							<input type="hidden" name="nom[]" value="'.$nom.'" />
						    </td></tr>';

                   //file_put_contents('/var/www/prairial/share/plugins/custom/idplus/octest',"FOUND $numFound_idref $test");
					$authform.= '<tr><td colspan="2" style="padding-left:20px;"><label style="display:inline-block;width:30%">';
					if ($numFound_idref) {
					    $authform.= '<a style="color:#ff5b04"'.$idreflink.'>IDREF('.$numFound_idref.')</a>';
					} else {
					    $authform.='IDREF';
					}
					$authform.='</label><input style="max-width:70%;" type="text" name="idref[]" value="'.$idref.'"/><br />';

					$authform.= '<label style="display:inline-block;width:30%;">';
					if ($numFound_orcid) { 
					    $authform.= '<a style="color:#ff5b04"'.$orcidlink.'>ORCID('.($numFound_orcid < 6 ? $numFound_orcid : '5+').')</a>';
					} else {
					    $authform.='ORCID';
					}
					$authform.= '</label><input style="max-width: 70%;margin-top:5px" name="orcid[]" value="'.$orcid.'" /></td></tr>';
                }
			}
			
			$authform.= '</table><input type="hidden" name="iddocument" value="'.$iddocument.'"/>
			<input type="submit" value="Enregistrer" />
			</form></div>';  
			View::$page = preg_replace('/(<\/div>\s*<\/div>\s*<\/body>)/s',$authform.'$1',View::$page);
		}
	}

	public function recordAction(&$context,&$errors) 
	{
		$siteurl = $context['siteurl'];
		$idperson = $_POST['idperson'];
		$prenom = $_POST['prenom'];
		$nom = $_POST['nom'];
		$idref = $_POST['idref'];
		$orcid = $_POST['orcid'];
		$iddocument = $_POST['iddocument'];
		$site = $context['site'];
		$sitefile = '../../.././share/plugins/custom/idplus/data/'.$site.'.json';
		if (file_exists($sitefile)) { 
			$json_data = file_get_contents($sitefile);
			$refs =  json_decode($json_data, true);
			for ($i = 0; $i < count($idperson); $i++) {
				$refs[$idperson[$i]] = array (
								  'nom' => $nom[$i],
								  'prenom' => $prenom[$i],
								  'idref' => $idref[$i],
								  'orcid' => $orcid[$i],
								 );
				if (!isset($refs[$idperson[$i]]['articles'])) 
				     $refs[$idperson[$i]]['articles'] = array();
				array_push($refs[$idperson[$i]]['articles'], $iddocument);
			}
			$json =  json_encode($refs, true);
			file_put_contents($sitefile, $json);
		}

        header("Location: $siteurl/index.php?$iddocument");

        return  "_ajax";

    }

}
