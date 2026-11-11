<?php

$issue = require __DIR__.'/../ParallelStopOnSharedIssue.php';

it('shares warning', $issue(E_USER_WARNING));

it('shares notice', $issue(E_USER_NOTICE));
