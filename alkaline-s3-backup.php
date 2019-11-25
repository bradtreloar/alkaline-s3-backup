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

$aws_config = "$home/.aws/alkaline-s3-backup";
if (file_exists($aws_config)) {
  $bucket = parse_ini_file($aws_config)['bucket'];
}
else {
  throw new \Exception("Unable to locate ~/.aws/alkaline-s3-backup.");
}

$archiver = new ArchiveBuilder($drupalRoot, $tmp);
$archive_filepath = $archiver->buildArchive();

$s3Client = new S3Client([
  'profile' => 'default',
  'version' => 'latest',
]);

$key = basename($archive_filepath);
$result = $s3Client->putObject([
  'Bucket' => $bucket,
  'Key' => $key,
  'SourceFile' => $archive_filepath,
]);

exec("rm $archive_filepath");
