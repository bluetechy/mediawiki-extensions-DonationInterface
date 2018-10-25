<?php

/**
 * Ingenico's connect API returns a 400 BAD REQUEST if the locale string
 * doesn't match the xx_YY format. This class tries to find a decent ALPHA2
 * code for any given ALPHA3 language code.
 */
class IngenicoLocale extends DonorLocale {
	/**
	 * Some fallbacks are from the iana subtag registry
	 * http://www.iana.org/assignments/language-subtag-registry/language-subtag-registry
	 * awk '/Subtag/ {st=$2} /Macro/ {print st " " $2}' < language-subtag-registry | sort | uniq | grep '... ..$' | sed -e "s/^/'/" -e "s/ /' => '/" -e "s/$/',/"
	 * Some fallbacks from mediawiki's languages/messages directory
	 * grep "fallback = '..'" Messages[a-z][a-z][a-z].php | cut -c9-11,29-32 | tr [A-Z] [a-z] | sed -e "s/'/' => '/" -e "s/^/'/" -e 's/$/,/'
	 * @return array
	 */
	protected function getFallbacks() {
		return [
			'aae' => 'sq',
			'aao' => 'ar',
			'aat' => 'sq',
			'abh' => 'ar',
			'abs' => 'id',
			'abv' => 'ar',
			'ace' => 'id',
			'acm' => 'ar',
			'acq' => 'ar',
			'acw' => 'ar',
			'acx' => 'ar',
			'acy' => 'ar',
			'adf' => 'ar',
			'aeb' => 'ar',
			'aec' => 'ar',
			'afb' => 'ar',
			'ajp' => 'ar',
			'aln' => 'sq',
			'als' => 'sq',
			'anp' => 'hi',
			'apc' => 'ar',
			'apd' => 'ar',
			'arb' => 'ar',
			'arn' => 'es',
			'arq' => 'ar',
			'ars' => 'ar',
			'ary' => 'ar',
			'arz' => 'ar',
			'ast' => 'es',
			'atj' => 'fr',
			'auz' => 'ar',
			'avl' => 'ar',
			'ayc' => 'ay',
			'ayh' => 'ar',
			'ayl' => 'ar',
			'ayn' => 'ar',
			'ayp' => 'ar',
			'ayr' => 'ay',
			'azb' => 'az',
			'azj' => 'az',
			'ban' => 'id',
			'bar' => 'de',
			'bbz' => 'ar',
			'bcc' => 'fa',
			'bgn' => 'fa',
			'bhr' => 'mg',
			'bjn' => 'id',
			'bjq' => 'mg',
			'bmm' => 'mg',
			'bpy' => 'bn',
			'bqi' => 'fa',
			'btj' => 'ms',
			'btm' => 'id',
			'bug' => 'id',
			'bve' => 'ms',
			'bvu' => 'ms',
			'bxr' => 'ru',
			'bzc' => 'mg',
			'cdo' => 'zh',
			'ciw' => 'oj',
			'cjy' => 'zh',
			'ckb' => 'ku',
			'cmn' => 'zh',
			'cnr' => 'sh',
			'coa' => 'ms',
			'cpx' => 'zh',
			'cqu' => 'qu',
			'crj' => 'cr',
			'crk' => 'cr',
			'crl' => 'cr',
			'crm' => 'cr',
			'csb' => 'pl',
			'csw' => 'cr',
			'cwd' => 'cr',
			'czh' => 'zh',
			'czo' => 'zh',
			'dtp' => 'ms',
			'dty' => 'ne',
			'dup' => 'ms',
			'egl' => 'it',
			'ekk' => 'et',
			'eml' => 'it',
			'esi' => 'ik',
			'esk' => 'ik',
			'ext' => 'es',
			'fat' => 'ak',
			'ffm' => 'ff',
			'fit' => 'fi',
			'frc' => 'fr',
			'frp' => 'fr',
			'frr' => 'de',
			'fub' => 'ff',
			'fuc' => 'ff',
			'fue' => 'ff',
			'fuf' => 'ff',
			'fuh' => 'ff',
			'fui' => 'ff',
			'fuq' => 'ff',
			'fur' => 'it',
			'fuv' => 'ff',
			'gag' => 'tr',
			'gan' => 'zh',
			'gax' => 'om',
			'gaz' => 'om',
			'gcr' => 'fr',
			'glk' => 'fa',
			'gnw' => 'gn',
			'gor' => 'id',
			'gsw' => 'de',
			'gug' => 'gn',
			'gui' => 'gn',
			'gun' => 'gn',
			'hae' => 'om',
			'hak' => 'zh',
			'hji' => 'ms',
			'hrx' => 'de',
			'hsn' => 'zh',
			'hyw' => 'hy',
			'ike' => 'iu',
			'ikt' => 'iu',
			'inh' => 'ru',
			'jak' => 'ms',
			'jam' => 'en',
			'jax' => 'ms',
			'jut' => 'da',
			'kbp' => 'fr',
			'kby' => 'kr',
			'khk' => 'mn',
			'khw' => 'ur',
			'kiu' => 'tr',
			'kmr' => 'ku',
			'knc' => 'kr',
			'kng' => 'kg',
			'koi' => 'kv',
			'kpv' => 'kv',
			'krc' => 'ru',
			'krl' => 'fi',
			'krt' => 'kr',
			'ksh' => 'de',
			'kum' => 'ru',
			'kvb' => 'ms',
			'kvr' => 'ms',
			'kwy' => 'kg',
			'kxd' => 'ms',
			'lad' => 'es',
			'lbe' => 'ru',
			'lce' => 'ms',
			'lcf' => 'ms',
			'ldi' => 'kg',
			'lez' => 'ru',
			'lij' => 'it',
			'liv' => 'et',
			'liw' => 'ms',
			'lki' => 'fa',
			'lmo' => 'it',
			'lrc' => 'fa',
			'ltg' => 'lv',
			'luz' => 'fa',
			'lvs' => 'lv',
			'lzh' => 'zh',
			'lzz' => 'tr',
			'mai' => 'hi',
			'max' => 'ms',
			'mdf' => 'ru',
			'meo' => 'ms',
			'mfa' => 'ms',
			'mfb' => 'ms',
			'mhr' => 'ru',
			'min' => 'id',
			'mnp' => 'zh',
			'mnw' => 'my',
			'mqg' => 'ms',
			'mrj' => 'ru',
			'msh' => 'mg',
			'msi' => 'ms',
			'mui' => 'ms',
			'mvf' => 'mn',
			'mwl' => 'pt',
			'myv' => 'ru',
			'mzn' => 'fa',
			'nah' => 'es',
			'nan' => 'zh',
			'nap' => 'it',
			'nds' => 'de',
			'nhd' => 'gn',
			'npi' => 'ne',
			'nrm' => 'fr',
			'ojb' => 'oj',
			'ojc' => 'oj',
			'ojg' => 'oj',
			'ojs' => 'oj',
			'ojw' => 'oj',
			'olo' => 'fi',
			'orc' => 'om',
			'orn' => 'ms',
			'ors' => 'ms',
			'ory' => 'or',
			'otw' => 'oj',
			'pbt' => 'ps',
			'pbu' => 'ps',
			'pcd' => 'fr',
			'pdc' => 'de',
			'pdt' => 'de',
			'pel' => 'ms',
			'pes' => 'fa',
			'pfl' => 'de',
			'pga' => 'ar',
			'pih' => 'en',
			'plt' => 'mg',
			'pms' => 'it',
			'pnt' => 'el',
			'prs' => 'fa',
			'pse' => 'ms',
			'pst' => 'ps',
			'qub' => 'qu',
			'qud' => 'qu',
			'quf' => 'qu',
			'qug' => 'qu',
			'quh' => 'qu',
			'quk' => 'qu',
			'qul' => 'qu',
			'qup' => 'qu',
			'qur' => 'qu',
			'qus' => 'qu',
			'quw' => 'qu',
			'qux' => 'qu',
			'quy' => 'qu',
			'quz' => 'qu',
			'qva' => 'qu',
			'qvc' => 'qu',
			'qve' => 'qu',
			'qvh' => 'qu',
			'qvi' => 'qu',
			'qvj' => 'qu',
			'qvl' => 'qu',
			'qvm' => 'qu',
			'qvn' => 'qu',
			'qvo' => 'qu',
			'qvp' => 'qu',
			'qvs' => 'qu',
			'qvw' => 'qu',
			'qvz' => 'qu',
			'qwa' => 'qu',
			'qwc' => 'qu',
			'qwh' => 'qu',
			'qws' => 'qu',
			'qxa' => 'qu',
			'qxc' => 'qu',
			'qxh' => 'qu',
			'qxl' => 'qu',
			'qxn' => 'qu',
			'qxo' => 'qu',
			'qxp' => 'qu',
			'qxr' => 'qu',
			'qxt' => 'qu',
			'qxu' => 'qu',
			'qxw' => 'qu',
			'rgn' => 'it',
			'rmy' => 'ro',
			'rup' => 'ro',
			'sah' => 'ru',
			'scn' => 'it',
			'sco' => 'en',
			'sdc' => 'sc',
			'sdh' => 'fa',
			'sdn' => 'sc',
			'ses' => 'fr',
			'sgs' => 'lt',
			'shu' => 'ar',
			'skg' => 'mg',
			'sli' => 'de',
			'spv' => 'or',
			'src' => 'sc',
			'srn' => 'nl',
			'sro' => 'sc',
			'ssh' => 'ar',
			'stq' => 'de',
			'sty' => 'ru',
			'swc' => 'sw',
			'swh' => 'sw',
			'szl' => 'pl',
			'tcy' => 'kn',
			'tdx' => 'mg',
			'tet' => 'pt',
			'tkg' => 'mg',
			'tmw' => 'ms',
			'txy' => 'mg',
			'tyv' => 'ru',
			'udm' => 'ru',
			'urk' => 'ms',
			'uzn' => 'uz',
			'uzs' => 'uz',
			'vec' => 'it',
			'vep' => 'et',
			'vkk' => 'ms',
			'vkt' => 'ms',
			'vls' => 'nl',
			'vmf' => 'de',
			'vot' => 'fi',
			'vro' => 'et',
			'wuu' => 'zh',
			'xal' => 'ru',
			'xmf' => 'ka',
			'xmm' => 'ms',
			'xmv' => 'mg',
			'xmw' => 'mg',
			'ydd' => 'yi',
			'yih' => 'yi',
			'yue' => 'zh',
			'zch' => 'za',
			'zeh' => 'za',
			'zgb' => 'za',
			'zgm' => 'za',
			'zgn' => 'za',
			'zhd' => 'za',
			'zhn' => 'za',
			'zlj' => 'za',
			'zlm' => 'ms',
			'zln' => 'za',
			'zlq' => 'za',
			'zmi' => 'ms',
			'zqe' => 'za',
			'zsm' => 'ms',
			'zyb' => 'za',
			'zyg' => 'za',
			'zyj' => 'za',
			'zyn' => 'za',
			'zzj' => 'za',
		];
	}
}