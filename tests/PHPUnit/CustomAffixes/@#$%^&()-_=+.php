<?php

/*
 * NOTE: To preserve cross-platform testing compatibility we cannot use ! * and
 * other Windows reserved characters in this test's filename.
 *
 * See https://docs.microsoft.com/en-us/windows/win32/fileio/naming-a-file#naming-conventions
 */

it(sprintf('runs file names like `%s`', basename(__FILE__)))->assertTrue(true);
