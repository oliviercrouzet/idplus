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

			global $db;
			/*
			$sitefile = '../../.././share/plugins/custom/idplus/data/'.$site.'.json';
			if (! file_exists($sitefile)) { 
				touch($sitefile);
			}
			$json_data = file_get_contents($sitefile);
			$refs =  json_decode($json_data, true);
			*/
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
					//$result = $db->getrow(lq("SELECT * FROM #_TP_identifiants WHERE id ='". $idperson. "'"));
				$idref = $db->getOne(lq("select distinct(idref) from #_TP_relations join #_TP_entities_auteurs using(idrelation) where id2='".$idperson."' and nature='G' and idref is not null and idref !=''"));
					//$result= $db->getrow(lq("SELECT idref FROM entities_auteurs,relations WHERE id2='".$idperson."' and nature='G' and degree=1 and relations.idrelation=entities_auteurs.idrelation"));
//					file_put_contents("/var/www/prairial/octest",print_r($result,true)." ".$idperson,FILE_APPEND);
$enregistrer=1;
					if ($idref === false) {
						trigger_error("SQL ERROR :<br />".$GLOBALS['db']->ErrorMsg(), E_USER_ERROR);
					}
					if (isset($idref)) {
					//	file_put_contents('/var/www/prairial/octest','RESULT'. count($idref));
						$emptyIdrefFound = $db->getOne(lq("select count(IFNULL(idref,'')) from #_TP_relations join #_TP_entities_auteurs using(idrelation) where id2='".$idperson."' and nature='G' and (idref is null or idref ='')"));
						$enregistrer = $emptyIdrefFound;
					}
					/*
					if ($idref) {
						$result = $db->getrow(lq("select idrelation from entities_auteurs join relations using(idrelation) where id2='".$idperson."' and nature='G' and (idref is null or idref='')"));
						//file_put_contents('/var/www/prairial/octest','RESULT '.$result['idperson']);
						$enregistrer = count($result);
					}
					*/
					$numFound_idref=0;
					if (!$idref) {
						$enregistrer=1;
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

					/*
					$authform.= '<label style="display:inline-block;width:30%;">';
					if ($numFound_orcid) { 
						$authform.= '<a style="color:#ff5b04"'.$orcidlink.'>ORCID('.($numFound_orcid < 6 ? $numFound_orcid : '5+').')</a>';
					} else {
						$authform.='ORCID';
					}
					$authform.= '</label><input style="max-width: 70%;margin-top:5px" name="orcid[]" value="'.$orcid.'" /></td></tr>';
					*/

					// autres ressources
					if ($idref) {
						$extraIds = $this->getExtraIds($idref);
						foreach ($extraIds as $source => $id) {
							if ($id) {
								$html = '<label style="display:inline-block;width:30%;">'.$source.'</label>';
								$html.= '<input style="max-width: 70%;margin-top:5px" name="'.$source.'[]" value="'.$id.'" />';
								$authform.= $html;
							}
						}
					}
					$authform.= '</td></tr>';
				}
			}
			
			$authform.= '</table>';
			if ($enregistrer) {
				$authform.= '<input type="hidden" name="iddocument" value="'.$iddocument.'"/>
							<input type="submit" value="Enregistrer" />';
			}

			$authform .= '</form></div>';  
			View::$page = preg_replace('/(<\/div>\s*<\/div>\s*<\/body>)/s',$authform.'$1',View::$page);
		}
	}

	private function getExtraIds($idref)
	{

		/* interrogation ABES */
		$json = file_get_contents("https://www.idref.fr/services/idref2id/$idref&format=text/json");
		if ($json !== FALSE) {
			$response = json_decode($json,true);
			$sources = $response['sudoc'];
			$idents = array();
			if (! empty($sources)){
				foreach ($sources as $key=>$val) {
					if ( is_numeric($key) ) {
						$type = $val['query']['result']['source'];
						$id = $val['query']['result']['identifiant'];
					} else {
						$type = $val['result']['source'];
						$id = $val['result']['identifiant'];
					}
					$idents[$type] = $id;

				}
			}
		}
		return $idents;
	}

	public function recordAction(&$context,&$errors) 
	{
		$siteurl = $context['siteurl'];
		$idperson = $_POST['idperson'];
		$prenom = $_POST['prenom'];
		$nom = $_POST['nom'];
		$idref = $_POST['idref'];
		//$orcid = $_POST['orcid'];
		$iddocument = $_POST['iddocument'];
		$site = $context['site'];
		global $db;
		//$q = "INSERT INTO #_TP_identifiants (id, nom, prenom, idref, articles)";
		/*
		$sitefile = '../../.././share/plugins/custom/idplus/data/'.$site.'.json';
		if (file_exists($sitefile)) { 
			$json_data = file_get_contents($sitefile);
			$refs =  json_decode($json_data, true);
	*/	
			for ($i = 0; $i < count($idperson); $i++) {
				$id = $idperson[$i];
				$ref = $idref[$i];
				//$q .= " VALUES('".$idperson[$i]. "', '". $nom[$i]. "', '". $prenom[$i]. "', '". $idref[$i]. "', concat(articles,'". $iddocument. "'))";
				// update document courant
				$q = "update entities_auteurs join relations using(idrelation) set idref='$ref' where id2='$id' and nature='G' and id1 = '$iddocument'";
				// update tous les documents du même auteur non renseignés
				$q = "update entities_auteurs join relations using(idrelation) set idref='$ref' where id2='$id' and nature='G'";
				$q .= " and (idref is null or idref='')";
				$result = $db->execute(lq($q));
				if ($result === false) {
					trigger_error("SQL ERROR :<br />".$GLOBALS['db']->ErrorMsg(), E_USER_ERROR);
				}
/*
				$refs[$idperson[$i]] = array (
								  'nom' => $nom[$i],
								  'prenom' => $prenom[$i],
								  'idref' => $idref[$i],
								  'orcid' => $orcid[$i],
								 );
				if (!isset($refs[$idperson[$i]]['articles'])) 
					 $refs[$idperson[$i]]['articles'] = array();
				array_push($refs[$idperson[$i]]['articles'], $iddocument);
				*/
			}
			/*
			$json =  json_encode($refs, true);
			file_put_contents($sitefile, $json);
		}*/

		header("Location: $siteurl/index.php?$iddocument");

		return  "_ajax";

	}

}
