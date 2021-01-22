<?php
/**
 * Aliases for special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'GlobalEditcount' => [ 'GlobalEditcount' ],
	'GlobalContributions' => [ 'GlobalContributions' ],
];

/** Serbian (Cyrillic script) (српски (ћирилица)) */
$specialPageAliases['sr-ec'] = [
	'GlobalEditcount' => [ 'ГлобалниБројИзмена' ],
	'GlobalContributions' => [ 'ГлобалниДоприноси' ],
];

/** Serbian (Latin script) (srpski (latinica)) */
$specialPageAliases['sr-el'] = [
	'GlobalEditcount' => [ 'GlobalniBrojIzmena' ],
	'GlobalContributions' => [ 'GlobalniDoprinosi' ],
];
