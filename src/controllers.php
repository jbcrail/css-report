<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app->get('/{hash}.css', function (Request $request, $hash) use ($app) {
  $uploaded_css = sprintf('%s/%s.css', $app['upload_path'], $hash);
  if (!file_exists($uploaded_css)) {
    $app->abort(404, "CSS does not exist");
  }
  return $app->sendFile($uploaded_css, 200, array('Content-Type' => 'text/css'), 'attachment');
});

$app->get('/{hash}.json', function (Request $request, $hash) use ($app) {
  $uploaded_json = sprintf('%s/%s.json', $app['upload_path'], $hash);
  if (!file_exists($uploaded_json)) {
    $app->abort(404, "JSON does not exist");
  }
  return $app->sendFile($uploaded_json, 200, array('Content-Type' => 'application/json'), 'attachment');
});

$app->get('/{hash}', function (Request $request, $hash) use ($app) {
  $uploaded_json = sprintf('%s/%s.json', $app['upload_path'], $hash);
  if (!file_exists($uploaded_json)) {
    $app->abort(404, "Report does not exist");
  }

  $summary = json_decode(file_get_contents($uploaded_json));
  $summary->unique_selectors = array_unique($summary->selectors);
  $fnGetProperty = function($decl) { return $decl->property; };
  $summary->unique_declarations = array_unique(array_map($fnGetProperty, $summary->declarations));
  $summary->unique_colors = array_unique($summary->colors);

  return $app['twig']->render('report.html', array('title' => 'CSS Report', 'summary' => $summary));
});

$app->get('/', function (Request $request) use ($app) {
  return $app['twig']->render('index.html', array('title' => 'CSS Report'));
});

$app->post('/', function (Request $request) use ($app) {
  if (!isset($_FILES['css'])) {
    $app->abort(500, "Undefined file upload parameter");
  }

  $file = $_FILES['css'];

  if (is_array($file['error'])) {
    $app->abort(500, "Multiple file uploads not allowed");
  }

  switch ($file['error']) {
    case UPLOAD_ERR_INI_SIZE:
      $app->abort(500, "The uploaded file exceeds the maximum file size defined by the server");
      break;

    case UPLOAD_ERR_FORM_SIZE:
      $app->abort(500, "The uploaded file exceeds the maximum file size defined by the HTML form");
      break;

    case UPLOAD_ERR_PARTIAL:
      $app->abort(500, "The uploaded file was only partially uploaded");
      break;

    case UPLOAD_ERR_NO_FILE:
      $app->abort(500, "No file was uploaded");
      break;

    case UPLOAD_ERR_NO_TMP_DIR:
      $app->abort(500, "No temporary folder available for uploaded files");
      break;

    case UPLOAD_ERR_CANT_WRITE:
      $app->abort(500, "Failed to write file to disk");
      break;

    case UPLOAD_ERR_EXTENSION:
      $app->abort(500, "The uploaded file was rejected by the server for an unknown reason");
      break;
  }

  if ($file['size'] > $app['upload_max_size']) {
    $app->abort(500, "Exceeded filesize limit");
  }

  // Since the file extension and mimetype of the uploaded file is not
  // reliable, we can't use these values. Since CSS must be encoded in
  // UTF-8, then we check to see if the uploaded file is encoded in
  // ASCII or UTF-8.
  //
  // Also, finfo_file() and mime_content_type() are not reliable
  // mimetype detectors.
  $encodings[] = "ASCII";
  $encodings[] = "UTF-8";
  if (mb_detect_encoding(file_get_contents($file['tmp_name']), $encodings) === FALSE) {
    $app->abort(500, "Illegal encoding of the uploaded CSS file");
  }

  // Calculate SHA-1 of uploaded file
  $hash = sha1_file($file['tmp_name']);
  $uploaded_css = sprintf('%s/%s.css', $app['upload_path'], $hash);
  $uploaded_json = sprintf('%s/%s.json', $app['upload_path'], $hash);

  // If CSS content was previously uploaded, redirect to report
  if (file_exists($uploaded_css)) {
    return $app->redirect('/'.$hash);
  }

  // Move uploaded file from temporary directory
  if (!move_uploaded_file($file['tmp_name'], $uploaded_css)) {
    $app->abort(500, "Unable to move uploaded file");
  }

  // Parse CSS and output results into JSON
  $parser = new CssParser($uploaded_css);
  $report = array(
    'selectors' => $parser->getSelectors(),
    'declarations' => $parser->getDeclarations(),
    'colors' => $parser->getColors()
  );

  // If using PHP 5.4 or higher, pretty print JSON
  $json = (PHP_VERSION_ID < 50400) ? json_encode($report) : json_encode($report, JSON_PRETTY_PRINT);
  file_put_contents($uploaded_json, $json);

  return $app->redirect('/'.$hash);
});

$app->error(function (\Exception $e, $code) use ($app) {
  if ($app['debug']) {
    return;
  }

  // 404.html, or 40x.html, or 4xx.html, or error.html
  $templates = array(
    'errors/'.$code.'.html',
    'errors/'.substr($code, 0, 2).'x.html',
    'errors/'.substr($code, 0, 1).'xx.html',
    'errors/default.html',
  );

  return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
