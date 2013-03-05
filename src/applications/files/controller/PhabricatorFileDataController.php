<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->key  = $data['key'];
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    $uri = new PhutilURI($alt);
    $alt_domain = $uri->getDomain();
    if ($alt_domain && ($alt_domain != $request->getHost())) {
      return id(new AphrontRedirectResponse())
        ->setURI($uri->setPath($request->getPath()));
    }

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $this->phid);
    if (!$file) {
      return new Aphront404Response();
    }

    if (!$file->validateSecretKey($this->key)) {
      return new Aphront403Response();
    }

    $data = $file->loadFileData();
    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);

    $is_viewable = $file->isViewableInBrowser();
    $force_download = $request->getExists('download');

    if ($is_viewable && !$force_download) {
      $response->setMimeType($file->getViewableMimeType());
    } else {
      if (!$request->isHTTPPost()) {
        // NOTE: Require POST to download files. We'd rather go full-bore and
        // do a real CSRF check, but can't currently authenticate users on the
        // file domain. This should blunt any attacks based on iframes, script
        // tags, applet tags, etc., at least. Send the user to the "info" page
        // if they're using some other method.
        return id(new AphrontRedirectResponse())
          ->setURI(PhabricatorEnv::getProductionURI($file->getBestURI()));
      }
      $response->setMimeType($file->getMimeType());
      $response->setDownload($file->getName());
    }

    return $response;
  }
}