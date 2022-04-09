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
					file_put_contents("/var/www/prairial/octest",'PERSONS '.print_r($persons,true),FILE_APPEND);

			global $db;
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
					$idref = $p['data']['idref'];
					$authform.= $this->buildIdrefPart($idref,$nom,$prenom,$idperson);

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
			$authform.= '<input type="hidden" name="iddocument" value="'.$iddocument.'"/>
							<input type="submit" value="Enregistrer" />';
			$authform .= '</form></div>';  
			View::$page = preg_replace('/(<\/div>\s*<\/div>\s*<\/body>)/s',$authform.'$1',View::$page);
		}
	}

	private function buildIdrefPart ($idref,$nom,$prenom,$idperson)
	{
		$html.= '<tr><td colspan="2" style="font-size:1.1em;color:#8a8a8a"><strong>'.$prenom.' '.$nom.'</strong>
				<input type="hidden" name="idperson[]" value="'.$idperson.'" />
				<input type="hidden" name="prenom[]" value="'.$prenom.'" />
				<input type="hidden" name="nom[]" value="'.$nom.'" />
				</td></tr>';

		$html.= '<tr><td colspan="2" style="padding-left:20px;"><label style="display:inline-block;width:30%">';

		if (!$idref) {
			$found = $this->searchIdrefCandidate($nom,$prenom);
			if ($found['occurrences']) {
				$occurrences = $found['occurrences'];		    
				$urls = $found['urls'];		    
				$html.= '<a style="color:#ff5b04"'.$urls.'>IDREF('.$occurrences.')</a>';
			} else {
				$html.='IDREF';
			}
		}
		$html.='</label><input style="max-width:70%;" type="text" name="idref[]" value="'.$idref.'"/><br />';

		return $html;
	}

	private function searchIdrefCandidate($nom,$prenom)
	{
		$xml = simplexml_load_file("https://www.idref.fr/Sru/Solr?q=persname_t:(".urlencode($nom." AND ".$prenom).")&fl=ppn_z");
		$numFound_idref = (int) $xml->result['numFound'];
		$idreflink='';
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
		return ['urls' => $idreflink,'occurrences' => $numFound];
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
		$idpersons = $_POST['idperson'];
		$prenoms = $_POST['prenom'];
		$noms = $_POST['nom'];
		$idrefs = $_POST['idref'];
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
				$id = $idpersons[$i];
				$ref = $idrefs[$i];
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
