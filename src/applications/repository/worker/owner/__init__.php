<?php
/**
 * This file is automatically generated. Lint this module to rebuild it.
 * @generated
 */



phutil_require_module('phabricator', 'applications/audit/constants/status');
phutil_require_module('phabricator', 'applications/differential/storage/revision');
phutil_require_module('phabricator', 'applications/owners/query/path');
phutil_require_module('phabricator', 'applications/owners/storage/owner');
phutil_require_module('phabricator', 'applications/owners/storage/package');
phutil_require_module('phabricator', 'applications/repository/storage/auditrequest');
phutil_require_module('phabricator', 'applications/repository/storage/commitdata');
phutil_require_module('phabricator', 'applications/repository/worker/base');
phutil_require_module('phabricator', 'infrastructure/daemon/workers/storage/task');

phutil_require_module('phutil', 'utils');


phutil_require_source('PhabricatorRepositoryCommitOwnersWorker.php');
