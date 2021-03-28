<?php

uses()->group('integration')->in('Visual');
uses()->beforeEach(function (): void {
    $this->baz = 1;
})->in('Hooks');
