<?php

abstract class PhabricatorShineController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Shine');
    $page->setBaseURI('/shine/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x81\x82");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
