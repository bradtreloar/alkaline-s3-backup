<?php

use Alkaline\ArchiveBuilder;
use Aws\S3\S3Client;
use DrupalFinder\DrupalFinder;
use Webmozart\PathUtil\Path;


$cwd = isset($_SERVER['PWD']) && is_dir($_SERVER['PWD'])
  ? $_SERVER['PWD']
  : getcwd();

// Set up autoloader.
$loader = false;
if (file_exists($autoloadFile = __DIR__ . '/vendor/autoload.php')
  || file_exists($autoloadFile = __DIR__ . '/../autoload.php')
  || file_exists($autoloadFile = __DIR__ . '/../../autoload.php')
) {
  $loader = include_once($autoloadFile);
} else {
  throw new \Exception("Could not locate autoload.php. cwd is $cwd; __DIR__ is " . __DIR__);
}

$home = Path::getHomeDirectory();
$tmp = "$home/tmp/";

$drupalFinder = new DrupalFinder();
if ($drupalFinder->locateRoot($cwd)) {
  $drupalRoot = $drupalFinder->getDrupalRoot();
}
else {
  throw new \Exception("Unable to locate Drupal root.");
}

// Get bucket name from config.
$config_file = "$home/.aws/alkaline-s3-backup";
if (file_exists($config_file)) {
  $config = parse_ini_file($config_file);
  $bucket = $config['bucket'];
}
else {
  throw new \Exception("Unable to locate ~/.aws/alkaline-s3-backup.");
}

// Get region from AWS config.
$aws_config_file = "$home/.aws/config";
if (file_exists($aws_config_file)) {
  $aws_config = parse_ini_file($aws_config_file, TRUE);
  $region = $aws_config['default']['region'];
}
else {
  $region = 'us-east-1';
}

$archiver = new ArchiveBuilder($drupalRoot, $tmp);
$archive_filepath = $archiver->buildArchive();

$s3Client = new S3Client([
  'profile' => 'default',
  'version' => 'latest',
  'region' => $region,
]);

$key = basename($archive_filepath);
$result = $s3Client->putObject([
  'Bucket' => $bucket,
  'Key' => $key,
  'SourceFile' => $archive_filepath,
]);

exec("rm $archive_filepath");
