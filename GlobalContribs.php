<?php

$wgExtensionCredits['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'GlobalContribs',
		'author' => 'Adam Carter/UltrasonicNXT',
		'url' => 'https://github.com/Brickimedia/GlobalContribs',
		'descriptionmsg' => 'globalcontribs-desc',
		'version' => '1.1',
);

$wgAutoloadClasses[ 'SpecialGlobalContributions' ] = __DIR__ . '/SpecialGlobalContributions.php';
$wgSpecialPages[ 'GlobalContributions' ] = 'SpecialGlobalContributions';

$wgAutoloadClasses[ 'SpecialGlobalEditcount' ] = __DIR__ . '/SpecialGlobalEditcount.php';
$wgSpecialPages[ 'GlobalEditcount' ] = 'SpecialGlobalEditcount';

$wgExtensionMessagesFiles[ 'GlobalContribs' ] = __DIR__ . '/GlobalContribs.i18n.php';