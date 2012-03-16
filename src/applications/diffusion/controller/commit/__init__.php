<?php
/**
 * This file is automatically generated. Lint this module to rebuild it.
 * @generated
 */



phutil_require_module('phabricator', 'applications/audit/constants/action');
phutil_require_module('phabricator', 'applications/audit/constants/commitstatus');
phutil_require_module('phabricator', 'applications/audit/editor/comment');
phutil_require_module('phabricator', 'applications/audit/query/audit');
phutil_require_module('phabricator', 'applications/audit/storage/auditcomment');
phutil_require_module('phabricator', 'applications/audit/view/list');
phutil_require_module('phabricator', 'applications/differential/constants/changetype');
phutil_require_module('phabricator', 'applications/differential/view/changesetlistview');
phutil_require_module('phabricator', 'applications/diffusion/controller/base');
phutil_require_module('phabricator', 'applications/diffusion/data/pathchange');
phutil_require_module('phabricator', 'applications/diffusion/query/contains/base');
phutil_require_module('phabricator', 'applications/diffusion/query/pathchange/base');
phutil_require_module('phabricator', 'applications/diffusion/query/pathid/base');
phutil_require_module('phabricator', 'applications/diffusion/view/commentlist');
phutil_require_module('phabricator', 'applications/diffusion/view/commitchangetable');
phutil_require_module('phabricator', 'applications/draft/storage/draft');
phutil_require_module('phabricator', 'applications/markup/engine');
phutil_require_module('phabricator', 'applications/phid/handle/data');
phutil_require_module('phabricator', 'applications/repository/constants/repositorytype');
phutil_require_module('phabricator', 'applications/repository/storage/repository');
phutil_require_module('phabricator', 'infrastructure/celerity/api');
phutil_require_module('phabricator', 'infrastructure/env');
phutil_require_module('phabricator', 'infrastructure/javelin/api');
phutil_require_module('phabricator', 'storage/queryfx');
phutil_require_module('phabricator', 'view/form/base');
phutil_require_module('phabricator', 'view/form/control/select');
phutil_require_module('phabricator', 'view/form/control/submit');
phutil_require_module('phabricator', 'view/form/control/textarea');
phutil_require_module('phabricator', 'view/form/error');
phutil_require_module('phabricator', 'view/layout/panel');
phutil_require_module('phabricator', 'view/null');
phutil_require_module('phabricator', 'view/utils');

phutil_require_module('phutil', 'markup');
phutil_require_module('phutil', 'utils');


phutil_require_source('DiffusionCommitController.php');
