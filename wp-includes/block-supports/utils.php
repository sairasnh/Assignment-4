<?php
 function wp_should_skip_block_supports_serialization( $block_type, $feature_set, $feature = null ) { if ( ! is_object( $block_type ) || ! $feature_set ) { return false; } $path = array( $feature_set, '__experimentalSkipSerialization' ); $skip_serialization = _wp_array_get( $block_type->supports, $path, false ); if ( is_array( $skip_serialization ) ) { return in_array( $feature, $skip_serialization, true ); } return $skip_serialization; } 