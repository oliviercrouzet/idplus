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

		// le bloc identifiants auteurs ne doit pas s'afficher en deça du niveau Editeur
		$pluginrights = isset($this->_config['userrights']['value']) ? $this->_config['userrights']['value'] : 30;
		if ($context['view']['tpl'] == 'edit_entities_edition' && isset($context['persons']) && $context['lodeluser']['rights'] >= $pluginrights) {
			$persons = $context['persons'];
			$site = $context['site'];
			global $db;

			$authform='<div class="advancedFunc">
						<h4>Identifiants auteurs</h4>
						<form id="idplus" action="index.php/?do=_idplus_record" method="POST">
						<table class="translations" style="width:90%" cellspacing="0" cellpadding="5" border="0">';
			$iddocument=$context['iddocument'];

			foreach ($persons as $person => $aut) {
				foreach ($aut as $p) {
					$prenom = $p['data']['prenom'];
					$nom = $p['data']['nomfamille'];
					$idperson = $p['data']['idperson'];
					$idref = $p['data']['idref'];
					$authform.= $this->buildIdrefHtml($idref,$nom,$prenom,$idperson);

					/*
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
					*/
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

	private function buildIdrefHtml ($idref,$nom,$prenom,$idperson)
	{
		$html = '<tr><td colspan="2" style="font-size:1.1em;color:#8a8a8a"><strong>'.$prenom.' '.$nom.'</strong>
				<input type="hidden" name="idpersons[]" value="'.$idperson.'" />
				<input type="hidden" name="prenoms[]" value="'.$prenom.'" />
				<input type="hidden" name="noms[]" value="'.$nom.'" />
				</td></tr>';
		$html.= '<tr><td colspan="2" style="padding-left:20px;">';
		$color='';
		$label = 'IDREF</label>';
		if (!$idref) {
			$idref = $this->isAlreadySet($idperson);
			if (!$idref) {
				$found = $this->searchIdrefCandidate($nom,$prenom);
				if ($found['occurrences']) {
					$occurrences = $found['occurrences'];
					$urls = $found['urls'];
					$label = '<a style="color:#ff5b04"'.$urls.'>IDREF('.$occurrences.')</a></label>';
				}
			} else {
				$color="color:#ff5b04;";
			}
		}
		$html .= '<label style="display:inline-block;width:30%;'.$color.'">';
		$html.= $label.'<input style="max-width:70%;'.$color.'" type="text" name="idrefs[]" value="'.$idref.'"/><br />';

		return $html;
	}

	private function isAlreadySet ($idperson)
	{
		global $db;
		$q = "select distinct(idref) from relations join entities_auteurs using(idrelation) where id2 = '$idperson' and nature = 'G' and idref is not null and idref !=''";
		return $db->getOne(lq("$q"));
	}

	private function searchIdrefCandidate($nom,$prenom)
	{
		// api SOLR et idref2id : voir https://abes.fr/api-et-web-services/
		$xml = simplexml_load_file("https://www.idref.fr/Sru/Solr?q=persname_t:(".urlencode($nom." AND ".$prenom).")&fl=ppn_z");
		$numFound = (int) $xml->result['numFound'];
		$idreflink='';
		if ($numFound > 0) {
			$matches = [];
			foreach ($xml->xpath('//str[@name="ppn_z"]') as $name) {
				array_push($matches,'https://www.idref.fr/'.$name);
			}
			$idreflink = 'href="'.array_shift($matches).'" target="_blank" ';
			if (count($matches)) {
				$idreflink.= 'onclick="';
				$i=0;
				foreach ($matches as $match) {
					$idreflink.= 'window.open(&quot;'.$match.'&quot;);';
					// on limite le nombre d'onglets ouvrables
					if (++$i == 10) break;
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
					$type = $type == 'BNF' ? 'ARK' : $type;
					if ($type == 'UNIV-DROIT') $id = preg_replace('/^.*\//','',$id);
					$idents[$type] = $id;

				}
			}
		}
		return $idents;
	}

	public function recordAction(&$context,&$errors) 
	{
		$siteurl = $context['siteurl'];
		$idpersons = $_POST['idpersons'];
		$prenoms = $_POST['prenoms'];
		$noms = $_POST['noms'];
		$idrefs = $_POST['idrefs'];
		$iddocument = $_POST['iddocument'];
		$site = $context['site'];
		global $db;

		// extraire la liste des champs identifiants présents dans la table entities_auteurs
		$q = "SELECT  group_concat(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'lodeldb_$site' AND TABLE_NAME = 'entities_auteurs'";
		$result = $db->getOne(lq("$q"));
		$fields = explode(',',$result);
		$idfields = array_slice($fields,8);

		for ($i = 0; $i < count($idpersons); $i++) {
			$idperson = $idpersons[$i];
			$idref = $idrefs[$i];

			// update document courant
			//$q = "update entities_auteurs join relations using(idrelation) set idref='$ref' where id2='$id' and nature='G' and id1 = '$iddocument'";
			// non renseigné
			//$q .= " and (idref is null or idref='')";

			// update sur tous les documents du même auteur
			$q = "update entities_auteurs join relations using(idrelation) set idref='$idref' where id2='$idperson' and nature='G'";
			$result = $db->execute(lq($q));
			if ($result === false) {
				trigger_error("SQL ERROR :<br />".$GLOBALS['db']->ErrorMsg(), E_USER_ERROR);
			}

			if ($idref) {
				$extraIds = $this->getExtraIds($idref);
				foreach ($extraIds as $s => $id) {
					$src = strtolower($s);
					$src = str_replace('-','',$src);
					if (in_array($src,$idfields)) {
						$q = "update entities_auteurs join relations using(idrelation) set $src='$id' where id2='$idperson' and nature='G'";
						$result = $db->execute(lq($q));
						if ($result === false) {
							trigger_error("SQL ERROR :<br />".$GLOBALS['db']->ErrorMsg(), E_USER_ERROR);
						}
					}
				}

			} else {
				// si l'idref a été supprimé, les autres identifiant doivent l'être aussi
				foreach ($idfields as $field) {
					$q = "update entities_auteurs join relations using(idrelation) set $field='' where id2='$idperson' and nature='G'";
					$result = $db->execute(lq($q));
					if ($result === false) {
						trigger_error("SQL ERROR :<br />".$GLOBALS['db']->ErrorMsg(), E_USER_ERROR);
					}
				}
			}
		}

		header("Location: $siteurl/index.php?$iddocument");

		return  "_ajax";

	}

}
