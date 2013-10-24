<?php

$wgExtensionCredits[ 'specialpage' ][] = array(
		'path' => __FILE__,
		'name' => 'GlobalContribs',
		'author' => 'Adam Carter/UltrasonicNXT',
		'url' => '',
		'descriptionmsg' => 'myextension-desc',
		'version' => '1.0',
);

$wgAutoloadClasses[ 'SpecialGlobalContributions' ] = __DIR__ . '/SpecialGlobalContributions.php';
$wgSpecialPages[ 'GlobalContributions' ] = 'SpecialGlobalContributions';

$wgExtensionMessagesFiles[ 'GlobalContribs' ] = __DIR__ . '/GlobalContribs.i18n.php';