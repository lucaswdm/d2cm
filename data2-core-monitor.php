<?php 

error_reporting(0);

$VET = array(
    'from' => 'data2',
    'date' => date('Y-m-d H:i:s'),
);

$VET['cpu'] = sys_getloadavg();
$VET['cores'] = intval(trim(shell_exec("cat /proc/cpuinfo | grep vendor_id | wc -l")));
$VET['mem'] = getSystemMemInfo();

$VET['services'] = array(
    'mysql' => (int) (bool) (fsockopen('localhost', 3306, $A, $B, 3)),
);

$VET['storage'] = getStorage();

$VET['network'] = testeConectividade();

$VET['domains'] = getDataDomains();

#$VET['http'] = file_get_contents('https://s3.waw.cloud.ovh.net/');

header("Content-type: application/json");
echo json_encode($VET, JSON_PRETTY_PRINT);



function testeConectividade() {

    $SERVERS = array(

        'nbg.icmp.hetzner.com' => array(),

        'fsn.icmp.hetzner.com' => array(),

        'hel.icmp.hetzner.com' => array(),

        'ash.icmp.hetzner.com' => array(),

        's3.us-east-1.amazonaws.com' => array(),
        's3.us-east-2.amazonaws.com' => array(),
        's3.us-west-1.amazonaws.com' => array(),
        's3.us-west-2.amazonaws.com' => array(),
        's3.ca-central-1.amazonaws.com' => array(),
        's3.eu-central-1.amazonaws.com' => array(),
        's3.eu-west-2.amazonaws.com' => array(),
        's3.sa-east-1.amazonaws.com' => array(),

        'nyc3.digitaloceanspaces.com' => array(),

        's3.sbg.cloud.ovh.net' => array(),
        's3.gra.cloud.ovh.net' => array(),
        's3.uk.cloud.ovh.net' => array(),
        's3.de.cloud.ovh.net' => array(),
        's3.waw.cloud.ovh.net' => array(),

        'objectstorage.sa-saopaulo-1.oraclecloud.com' => array('port' => 443, 'mincache' => 60, 'maxcache' => 120 ),

        '1.1.1.1' => array('port' => 80),
        'dns.google' => array('port' => 443),

        
        'ashburn-colocrossing.data2.com.br' => array('port' => 80, 'mincache' => 30, 'maxcache' => 60 ),
        'lg.atl.colocrossing.com' => array('port' => 80, 'mincache' => 30, 'maxcache' => 60 ),
        
        'lg.dal.colocrossing.com' => array('port' => 80, 'mincache' => 30, 'maxcache' => 60 ),
        'lg.nyc.colocrossing.com' => array('port' => 80, 'mincache' => 30, 'maxcache' => 60 ),
    );

    $T =  array();

    foreach($SERVERS as $S => $CONF) {
        $T[$S] = __wTestExternalNetwork($S,$CONF['port'] ?? 80, $CONF['lat'] ?? 3, $CONF['mincache'] ?? 10, $CONF['maxcache'] ?? 45);
    }

    return $T;
}

function getSystemMemInfo() 
{       
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = array();
    foreach ($data as $line) {
        list($key, $val) = explode(":", $line);
        $key = strtolower($key);
        if(in_array($key, array('memtotal', 'memfree', 'memavailable', 'cached')))
        {
            $meminfo[$key] = size2MB(trim($val));
        }        
    }
    return $meminfo;
}

function size2MB($X)
{
    if(preg_match('/([0-9]{1,}).*([A-Z]{2})/i', $X, $dados)) {
        if($dados[2] == 'kB') return intval($dados[1] / 1024);
    }

    return intval($X);
}


function getStorage() {

    $FILE_MEMORY = '/dev/shm/data2-core-monitor-storage.json';

    if(is_file($FILE_MEMORY)) {
        $DIFF = time() - filemtime($FILE_MEMORY);
        if($DIFF < 300) {
            return json_decode(file_get_contents($FILE_MEMORY), true);
        }
    }

    $SHELL = array_slice(explode(PHP_EOL, trim(shell_exec("df -T  -P -B 1M"))),1);
    #print_R($SHELL);
    
    $R = array();

    foreach($SHELL as $line) {
        $EXP = array_values(array_filter(explode(" ", $line), function($item) {
            return $item != '';
        }));

        if(count($EXP) == 7) {
            if($EXP[1] == 'squashfs') continue;
            $R[$EXP[6]] = array(
                'total' => $EXP[2],
                'used' => $EXP[3],
            );
        }
        #print_r($EXP);        
    }

    file_put_contents($FILE_MEMORY, json_encode($R));

    return $R;
}

function __wTestExternalNetwork($HOST, $PORT = 80, $MAX = 2.5) {
    $FILE = '/dev/shm/D2CM-' . $HOST . '_' . $PORT;
    
    if(is_file($FILE)) {
        $DIFF = time() - filemtime($FILE);
        if($DIFF < rand(10,45) ) {
            return json_decode(file_get_contents($FILE), true);
        }
    }

    $T0 = microtime(true);
    $FSOCK = (int) (bool) fsockopen($HOST, $PORT, $A, $B, $MAX);
    $DIFF = microtime(true) - $T0;
    $VET = array($FSOCK, (float) round($DIFF,$MAX));
    file_put_contents($FILE, json_encode($VET));
    return $VET;
}

function getDataDomains() {

    $FILE = '/dev/shm/D2CM-domains';

    if(is_file($FILE)) {
        $DIFF = time() - filemtime($FILE);
        if($DIFF < 300 ) {
            //echo "CACHED GDD";
            return json_decode(file_get_contents($FILE), true);
        }
    }

    $VET = array();

    foreach(glob('/data/*/') as $dir) {
        if(is_dir($dir)) {
            $BASENAME = basename($dir);
            if(strpos($BASENAME,'.') !== false && preg_match('/^[a-z0-9\.\-]{3,}$/', $BASENAME)) {
                $VET[basename($dir)] = 1;
            }
        }
    }

    #return $VET;

    file_put_contents($FILE, json_encode($VET));

    return $VET;
}
