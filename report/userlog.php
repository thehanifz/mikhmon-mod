<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

	$idhr = $_GET['idhr'];
	$idbl = $_GET['idbl'];
	$remdata = ($_POST['remdata']);

	// idbl2: v6=mar2025, v7=2026-03
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $idhr)) {
		$idbl2 = substr($idhr, 0, 7);
	} else {
		$idbl2 = explode("/", $idhr)[0] . explode("/", $idhr)[2];
	}

	// Helper display bulan
	$_mfull = [1=>"January","February","March","April","May","June","July","August","September","October","November","December"];
	$_msht  = [1=>"jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"];
	function fmtIdbl($s, $_msht, $_mfull) {
		if (preg_match('/^(\d{4})-(\d{2})$/', $s, $m)) return ($_mfull[(int)$m[2]] ?? '') . ' ' . $m[1];
		return ucfirst(substr($s,0,3)) . ' ' . substr($s,3,4);
	}
	$idbl_display = $idbl ? fmtIdbl($idbl, $_msht, $_mfull) : '';

	if (strlen($idhr) > "0") {
		// Filter harian — exact source (v6) atau name~ (v7)
		$ARRAY = $API->comm("/system/script/print", array("?source" => "$idhr"));
		if (empty($ARRAY)) {
			$ARRAY = $API->comm("/system/script/print", array("~name" => "^$idhr"));
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "text";
	} elseif (strlen($idbl) > "0") {
		// Server-side filter ~owner kedua format v6+v7, deduplicate by .id
		$_v6s = [1=>"jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"];
		if (preg_match('/^(\d{4})-(\d{2})$/', $idbl, $_mv)) {
			// Anchor ^ dan $ untuk exact match owner di ROS regex
		$_patterns = array_filter([$idbl, ($_v6s[(int)$_mv[2]] ?? '') . $_mv[1]]);
		} else {
			$_fi = array_search(substr($idbl,0,3), $_v6s);
			$_v7 = $_fi ? substr($idbl,3,4).'-'.str_pad($_fi,2,'0',STR_PAD_LEFT) : '';
			// Anchor ^ dan $ untuk exact match owner di ROS regex
		$_patterns = array_filter([$idbl, $_v7]);
		}
		// Pakai ?owner exact match (bukan ~owner) — API tidak support regex
		$ARRAY = [];
		foreach ($_patterns as $_pat) {
			if (empty($_pat)) continue;
			// Hapus anchor ^ dan $ kalau ada dari versi lama
			$_pat = trim($_pat, '^$');
			$_res = $API->comm("/system/script/print", array("?owner" => "$_pat"));
			foreach ($_res as $_row) {
				$_exists = false;
				foreach ($ARRAY as $_ex) {
					if (($_ex['.id'] ?? '') === ($_row['.id'] ?? '')) { $_exists = true; break; }
				}
				if (!$_exists) $ARRAY[] = $_row;
			}
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "text";
	} else {
		$ARRAY = $API->comm("/system/script/print", array("?comment" => "mikhmon"));
		$filedownload = "all";
		$shf = "text";
		$shd = "hidden";
	}
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  csvFile = new Blob([csv], {type: "text/csv"});
			  downloadLink = document.createElement("a");
			  downloadLink.download = filename;
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  downloadLink.style.display = "none";
			  document.body.appendChild(downloadLink);
			  downloadLink.click();
			}
			function exportTableToCSV(filename) {
			  var csv = [];
			  var rows = document.querySelectorAll("#dataTable tr");
			  for (var i = 0; i < rows.length; i++) {
			    var row = [], cols = rows[i].querySelectorAll("td, th");
			    for (var j = 0; j < cols.length; j++) row.push(cols[j].innerText);
			    csv.push(row.join(","));
			  }
			  downloadCSV(csv.join("\n"), filename);
			}
		</script>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-align-justify"></i> User Log <?= $idhr ? $idhr : $idbl_display; ?></h3>
</div>
<div class="card-body">
	<div>
		<div style="padding-bottom: 5px; padding-top: 5px; display: table-row;">
		  <input id="filterTable" type="text" class="form-control" style="float:left; margin-top: 6px; max-width: 150px;" placeholder="Search..">&nbsp;
		  <button class="btn bg-primary" onclick="exportTableToCSV('user-log-mikhmon-<?= $filedownload; ?>.csv')" title="Download user log"><i class="fa fa-download"></i> CSV</button>
		  <button class="btn bg-primary" onclick="location.href='./?report=userlog&session=<?= $session; ?>';" title="Reload all data"><i class="fa fa-search"></i> <?= $_all ?></button>
		</div>
		<div class="input-group mr-b-10">
			<div class="input-group-1 col-box-2">
			<select style="padding:5px;" class="group-item group-item-l" title="Day" id="D">
				<?php
				$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $idhr) ? explode("-",$idhr)[2] : explode("/",$idhr)[1];
				if ($day != "") echo "<option value='" . $day . "'>" . $day . "</option>";
				echo "<option value=''>Day</option>";
				for ($x = 1; $x <= 31; $x++) {
					$xp = str_pad($x, 2, "0", STR_PAD_LEFT);
					echo "<option value='" . $xp . "'>" . $xp . "</option>";
				}
				?>
			</select>
			</div>
			<div class="input-group-2 col-box-4">
			<select style="padding:5px;" class="group-item group-item-md" title="Month" id="M">
				<?php
				$idblf   = array(1=>"January","February","March","April","May","June","July","August","September","October","November","December");
				$idbls_sht = array(1=>"jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec");
				if (preg_match('/^\d{4}-(\d{2})-\d{2}$/', $idhr, $_dm)) {
					$selmon = (int)$_dm[1];
				} elseif ($idhr != "") {
					$selmon = array_search(explode("/",$idhr)[0], $idbls_sht) ?: date("n");
				} elseif (preg_match('/^\d{4}-(\d{2})$/', $idbl, $_bm)) {
					$selmon = (int)$_bm[1];
				} elseif ($idbl != "") {
					$selmon = array_search(substr($idbl,0,3), $idbls_sht) ?: date("n");
				} else {
					$selmon = (int)date("n");
				}
				echo "<option value='" . str_pad($selmon,2,'0',STR_PAD_LEFT) . "'>" . $idblf[$selmon] . "</option>";
				for ($x = 1; $x <= 12; $x++) {
					echo "<option value='" . str_pad($x,2,'0',STR_PAD_LEFT) . "'>" . $idblf[$x] . "</option>";
				}
				?>
			</select>
			</div>
			<div class="input-group-2 col-box-3">
			<select style="padding:5px;" class="group-item group-item-md" title="Year" id="Y">
				<?php
				$year  = preg_match('/^\d{4}-/', $idhr) ? explode("-",$idhr)[0] : explode("/",$idhr)[2];
				$year1 = preg_match('/^\d{4}-\d{2}$/', $idbl) ? explode("-",$idbl)[0] : substr($idbl,3,4);
				if ($year != "") echo "<option>" . $year . "</option>";
				elseif ($year1 != "") echo "<option>" . $year1 . "</option>";
				else echo "<option>" . date("Y") . "</option>";
				for ($Y = 2018; $Y <= date("Y"); $Y++) {
					if ($Y != date("Y")) echo "<option value='" . $Y . "'>" . $Y . "</option>";
				}
				?>
			</select>
			</div>
			<div class="input-group-2 col-box-3">
				<div style="padding:3.5px;" class="group-item group-item-r text-center pointer" onclick="filterR();"><i class="fa fa-search"></i> Filter</div>
			</div>
			<script type="text/javascript">
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;
					if(D !== ""){
						window.location='./?report=userlog&idhr='+Y+'-'+M+'-'+D+'&session=<?= $session; ?>';
					}else{
						window.location='./?report=userlog&idbl='+Y+'-'+M+'&session=<?= $session; ?>';
					}
				}
			</script>
		</div>
	</div>
	<div class="overflow box-bordered" style="max-height: 75vh;">
		<table id="dataTable" class="table table-bordered table-hover text-nowrap">
			<thead>
			<tr>
			  <th colspan=6>User Log <?= $filedownload; ?></th>
			</tr>
			<tr>
				<th><?= $_date ?></th>
				<th><?= $_time ?></th>
				<th><?= $_user_name ?></th>
				<th>address</th>
				<th>Mac Address</th>
				<th><?= $_validity ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
		$TotalReg = count($ARRAY);
		for ($i = 0; $i < $TotalReg; $i++) {
			$regtable = $ARRAY[$i];
			$getname = explode("-|-", $regtable['name']);
			echo "<tr>";
			echo "<td>" . $getname[0] . "</td>";
			echo "<td>" . $getname[1] . "</td>";
			echo "<td>" . $getname[2] . "</td>";
			echo "<td>" . $getname[4] . "</td>";
			echo "<td>" . $getname[5] . "</td>";
			echo "<td>" . $getname[6] . "</td>";
			echo "</tr>";
		}
		?>
			</tbody>
		</table>
		</div>
</div>
</div>
</div>
</div>