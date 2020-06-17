<?php

it('allows global uses')->assertPluginTraitGotRegistered();

it('allows multiple global uses registered in the same path')->assertSecondPluginTraitGotRegistered();
