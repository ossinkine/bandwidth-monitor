#!/usr/bin/php
<?php

$options = array();
$arguments = array();
$scriptName = array_shift($argv);
foreach ($argv as $argument) {
    if (strpos($argument, '--') === 0) {
        @list($name, $value) = explode('=', substr($argument, 2), 2);
        $options[$name] = $value;
    } else {
        $arguments[] = $argument;
    }
}

if (empty($arguments)) {
    printf('Usage: %s [--timeout=<seconds>] [--metric=<metric-prefix>] [--out=<filename>] <interface>' . PHP_EOL, $scriptName);
    exit;
}

$interface = reset($arguments);
$interfaces = array_diff(scandir('/sys/class/net'), array('..', '.'));
if (!in_array($interface, $interfaces)) {
    printf('Wrong interface, use one of this: %s' . PHP_EOL, implode(', ', $interfaces));
    exit;
}
$timeout = array_key_exists('timeout', $options) && (int) $options['timeout'] > 0 ? $options['timeout'] : 1;
$metric = array_key_exists('metric', $options) ? strtoupper($options['metric']) : null;
$metrics = array('K', 'M', 'G', 'T');
if (null !== $metric && !in_array($metric, $metrics)) {
    printf('Wrong metric, use one of this: %s' . PHP_EOL, implode(', ', $metrics));
    exit;
}
$filename = array_key_exists('out', $options) ? $options['out'] : null;

$in = 0;
$out = 0;
$time = 0;
while (true) {
    $lastIn = $in;
    $lastOut = $out;
    $lastTime = $time;
    $in = file_get_contents(sprintf('/sys/class/net/%s/statistics/rx_bytes', $interface));
    $out = file_get_contents(sprintf('/sys/class/net/%s/statistics/tx_bytes', $interface));
    $time = microtime(true);
    $timeDiff = $time - $lastTime;
    $formattedIn = formatSpeed(($in - $lastIn) / $timeDiff, $metric);
    $formattedOut = formatSpeed(($out - $lastOut) / $timeDiff, $metric);
    if (null !== $filename) {
        file_put_contents($filename, $formattedIn . PHP_EOL . $formattedOut . PHP_EOL);
    } else {
        echo $formattedIn . PHP_EOL;
        echo $formattedOut . PHP_EOL;
        echo PHP_EOL;
    }
    sleep($timeout);
}

function formatSpeed($bytesPerSeconds, $metric)
{
    $bitsPerSeconds = $bytesPerSeconds * 8;
    switch ($metric) {
        case 'T':
            $bitsPerSeconds /= 1024;
        case 'G':
            $bitsPerSeconds /= 1024;
        case 'M':
            $bitsPerSeconds /= 1024;
        case 'K':
            $bitsPerSeconds /= 1024;
    }

    return round($bitsPerSeconds, 2);
}
