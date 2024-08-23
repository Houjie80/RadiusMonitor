<?php
// PrintTickets daloRADIUS By Maizil
include "phpqrcode/qrlib.php";
$cs = "CS: 0853-7268-7484";
$dnsname = "http://mutiara.net";

$configValues = array(
    "CONFIG_DB_TBL_DALOBILLINGPLANS" => "billing_plans",
    "CONFIG_DB_TBL_RADGROUPREPLY" => "radgroupreply",
    "CONFIG_DB_TBL_RADGROUPCHECK" => "radgroupcheck" 
);

if (isset($_REQUEST["type"]) && $_REQUEST["type"] == "batch") {
    if (isset($_REQUEST['format'])) {
        $format = $_REQUEST['format'];

    } else {
        
    }

    $plan = $_REQUEST["plan"];
    $accounts_temp = $_REQUEST["accounts"];
    $accounts = explode("||", $accounts_temp);

    // Konfigurasi database
    $host = '127.0.0.1';
    $dbname = 'radius';
    $username = 'radius';
    $password = 'radius';

        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT planCost, planTimeBank, planCurrency FROM " . $configValues["CONFIG_DB_TBL_DALOBILLINGPLANS"] . " WHERE planName = :plan";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':plan', $plan, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $ticketCurrency = $row["planCurrency"];
            $ticketCost = $row["planCost"] . " " . $ticketCurrency;
            $ticketTime = time2str($row["planTimeBank"]);
            
        } else {
        $ticketCurrency = '0';
        $ticketCost = '0';
        $ticketTime = '';
        }

            $sqlQuota = "SELECT value FROM " . $configValues["CONFIG_DB_TBL_RADGROUPREPLY"] . " WHERE groupname = :plan AND attribute = 'ChilliSpot-Max-Total-Octets'";
            $stmtQuota = $pdo->prepare($sqlQuota);
            $stmtQuota->bindParam(':plan', $plan, PDO::PARAM_STR);
            $stmtQuota->execute();

            $quotaRow = $stmtQuota->fetch(PDO::FETCH_ASSOC);
            $ticketQuota = isset($quotaRow["value"]) ? formatBytes($quotaRow["value"]) : "";

            $sqlActiveTime = "SELECT value FROM " . $configValues["CONFIG_DB_TBL_RADGROUPCHECK"] . " WHERE groupname = :plan AND attribute = 'Max-All-Session'";
            $stmtActiveTime = $pdo->prepare($sqlActiveTime);
            $stmtActiveTime->bindParam(':plan', $plan, PDO::PARAM_STR);
            $stmtActiveTime->execute();

            $activeRow = $stmtActiveTime->fetch(PDO::FETCH_ASSOC);
            $ticketActiveTime = isset($activeRow["value"]) ? time2str($activeRow["value"]) : "";

            $timestamp = date('Y-m-d H:i:s');

            printTicketsHTMLTable($accounts, $ticketCost, $ticketTime, $ticketQuota, $ticketActiveTime, $timestamp);

}

function formatBytes($bytes, $decimal = 0) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%." . $decimal . "f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

function time2str($time) {
    if (!is_numeric($time)) {
        return '';
    }
    $units = array(
        "TAHUN" => 365*24*3600,
        "BULAN" => 30*24*3600,
        "HARI" => 24*3600,
        "JAM" => 3600,
        "MENIT" => 60,
        "DETIK" => 1,
    );

    $str = "";
    foreach ($units as $name => $divisor) {
        $quot = intval($time / $divisor);
        if ($quot) {
            $str .= "$quot $name ";
            $time -= $quot * $divisor;
        }
    }

    return trim($str);
}

function printTicketsHTMLTable($accounts, $ticketCost, $ticketTime, $ticketQuota, $ticketActiveTime, $timestamp)
{
    global $dnsname, $cs;

    if ($ticketCost <= 500) {
        $color = "#4bde97";
    } elseif ($ticketCost >= 1000 && $ticketCost <= 4000) {
        $color = "#e83e8c";
    } elseif ($ticketCost >= 4000 && $ticketCost <= 24000) {
        $color = "#f74e07";
    } elseif ($ticketCost >= 25000 && $ticketCost <= 49000) {
        $color = "#0f8d43";
    } elseif ($ticketCost >= 50000 && $ticketCost <= 100000) {
        $color = "#9911b1";
    }

    array_shift($accounts);
    foreach ($accounts as $userpass) {
        list($user, $pass) = explode(",", $userpass);

        $size = isset($_REQUEST["size"]) ? (int)$_REQUEST["size"] : 5;
        $matrixPointSize = min(max($size, 5), 10);

?>
<title><?php echo $ticketTime; ?></title>
<style type="text/css">.rotate {vertical-align: center;text-align: center;font-size: 20px;}.rotate span {-ms-writing-mode: tb-rl;-webkit-writing-mode: vertical-rl;writing-mode: vertical-rl;transform: rotate(180deg);white-space: nowrap;}</style>
<table style="display: inline-block;border-collapse: collapse;border: 1px solid #666;margin: 2.5px;width: 237px;overflow:hidden;position:relative;padding: 1px;margin: 0px;border: 1px solid <?php echo $color; ?>; background:; ">
<tr>
<td style="font-size: 13.5px; font-weight: bold; line white; color:#fff;background-color:<?php echo $color; ?>; -webkit-print-color-adjust: exact;" class="rotate" rowspan="4"><span>Rp.<?php echo $ticketCost; ?></span></td>
</div>
<td><center><span style="font-size: 13.5px;font-weight: bold;">𝗠𝗨𝗧𝗜𝗔𝗥𝗔<span style="color:<?php echo $color; ?>;">𝗡𝗘𝗧</td>
<td style="" rowspan="4">
<?php
$tempdir = "tmp/";
if (!file_exists($tempdir)) {
    mkdir($tempdir);
}

$qrcodeData = "$user";
$errorCorrectionLevel = "L";
QRcode::png(
    $qrcodeData,
    $tempdir . $user . ".png",
    $errorCorrectionLevel,
    $matrixPointSize,
    2
);
echo '<img src="' .
    $tempdir .
    $user .
    ".png" .
    '" style="border: 2px solid ' .
    $color .
    '; width: 65px; height: 65px;">';
?>
</td>
</tr>
<tr>
<td style="width: 100%; font-weight: bold; font-size: 18px; color:#111; text-align: center;"><?php echo $user; ?></td>
</tr>
<tr>
<td style="font-weight: bold; font-size: 10px; color:#000000;"><center><?php echo $ticketActiveTime; ?>  <?php echo $ticketTime; ?>  <?php echo $ticketQuota; ?> 
</tr>
<tr>
<td style="font-weight: bold; font-size: 10px; color:#000000;"><center><?php echo $cs; ?></td>
</tr>
</tbody>
</table> 
<?php } } ?> 