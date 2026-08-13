<?php

namespace Ezoic_Namespace;

class Ezoic_Content_Util {
	public static function is_error( $result ) {
		if ( gettype( $result ) === 'string' ) {
			return true;
		}

		return false;
	}
}
