<?php
 class WP_REST_Terms_Controller extends WP_REST_Controller { protected $taxonomy; protected $meta; protected $sort_column; protected $total_terms; protected $allow_batch = array( 'v1' => true ); public function __construct( $taxonomy ) { $this->taxonomy = $taxonomy; $tax_obj = get_taxonomy( $taxonomy ); $this->rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $tax_obj->name; $this->namespace = ! empty( $tax_obj->rest_namespace ) ? $tax_obj->rest_namespace : 'wp/v2'; $this->meta = new WP_REST_Term_Meta_Fields( $taxonomy ); } public function register_routes() { register_rest_route( $this->namespace, '/' . $this->rest_base, array( array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_items' ), 'permission_callback' => array( $this, 'get_items_permissions_check' ), 'args' => $this->get_collection_params(), ), array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_item' ), 'permission_callback' => array( $this, 'create_item_permissions_check' ), 'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), ), 'allow_batch' => $this->allow_batch, 'schema' => array( $this, 'get_public_item_schema' ), ) ); register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array( 'args' => array( 'id' => array( 'description' => __( 'Unique identifier for the term.' ), 'type' => 'integer', ), ), array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_item' ), 'permission_callback' => array( $this, 'get_item_permissions_check' ), 'args' => array( 'context' => $this->get_context_param( array( 'default' => 'view' ) ), ), ), array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_item' ), 'permission_callback' => array( $this, 'update_item_permissions_check' ), 'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ), ), array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_item' ), 'permission_callback' => array( $this, 'delete_item_permissions_check' ), 'args' => array( 'force' => array( 'type' => 'boolean', 'default' => false, 'description' => __( 'Required to be true, as terms do not support trashing.' ), ), ), ), 'allow_batch' => $this->allow_batch, 'schema' => array( $this, 'get_public_item_schema' ), ) ); } public function check_read_terms_permission_for_post( $post, $request ) { if ( ! is_object_in_taxonomy( $post->post_type, $this->taxonomy ) ) { return false; } if ( is_post_publicly_viewable( $post ) ) { return true; } if ( current_user_can( 'read_post', $post->ID ) ) { return true; } return false; } public function get_items_permissions_check( $request ) { $tax_obj = get_taxonomy( $this->taxonomy ); if ( ! $tax_obj || ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) { return false; } if ( 'edit' === $request['context'] && ! current_user_can( $tax_obj->cap->edit_terms ) ) { return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit terms in this taxonomy.' ), array( 'status' => rest_authorization_required_code() ) ); } if ( ! empty( $request['post'] ) ) { $post = get_post( $request['post'] ); if ( ! $post ) { return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400, ) ); } if ( ! $this->check_read_terms_permission_for_post( $post, $request ) ) { return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to view terms for this post.' ), array( 'status' => rest_authorization_required_code(), ) ); } } return true; } public function get_items( $request ) { $registered = $this->get_collection_params(); $parameter_mappings = array( 'exclude' => 'exclude', 'include' => 'include', 'order' => 'order', 'orderby' => 'orderby', 'post' => 'post', 'hide_empty' => 'hide_empty', 'per_page' => 'number', 'search' => 'search', 'slug' => 'slug', ); $prepared_args = array( 'taxonomy' => $this->taxonomy ); foreach ( $parameter_mappings as $api_param => $wp_param ) { if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) { $prepared_args[ $wp_param ] = $request[ $api_param ]; } } if ( isset( $prepared_args['orderby'] ) && isset( $request['orderby'] ) ) { $orderby_mappings = array( 'include_slugs' => 'slug__in', ); if ( isset( $orderby_mappings[ $request['orderby'] ] ) ) { $prepared_args['orderby'] = $orderby_mappings[ $request['orderby'] ]; } } if ( isset( $registered['offset'] ) && ! empty( $request['offset'] ) ) { $prepared_args['offset'] = $request['offset']; } else { $prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number']; } $taxonomy_obj = get_taxonomy( $this->taxonomy ); if ( $taxonomy_obj->hierarchical && isset( $registered['parent'], $request['parent'] ) ) { if ( 0 === $request['parent'] ) { $prepared_args['parent'] = 0; } else { if ( $request['parent'] ) { $prepared_args['parent'] = $request['parent']; } } } $prepared_args = apply_filters( "rest_{$this->taxonomy}_query", $prepared_args, $request ); if ( ! empty( $prepared_args['post'] ) ) { $query_result = wp_get_object_terms( $prepared_args['post'], $this->taxonomy, $prepared_args ); $prepared_args['object_ids'] = $prepared_args['post']; } else { $query_result = get_terms( $prepared_args ); } $count_args = $prepared_args; unset( $count_args['number'], $count_args['offset'] ); $total_terms = wp_count_terms( $count_args ); if ( ! $total_terms ) { $total_terms = 0; } $response = array(); foreach ( $query_result as $term ) { $data = $this->prepare_item_for_response( $term, $request ); $response[] = $this->prepare_response_for_collection( $data ); } $response = rest_ensure_response( $response ); $per_page = (int) $prepared_args['number']; $page = (int) ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 ); $response->header( 'X-WP-Total', (int) $total_terms ); $max_pages = (int) ceil( $total_terms / $per_page ); $response->header( 'X-WP-TotalPages', $max_pages ); $request_params = $request->get_query_params(); $collection_url = rest_url( rest_get_route_for_taxonomy_items( $this->taxonomy ) ); $base = add_query_arg( urlencode_deep( $request_params ), $collection_url ); if ( $page > 1 ) { $prev_page = $page - 1; if ( $prev_page > $max_pages ) { $prev_page = $max_pages; } $prev_link = add_query_arg( 'page', $prev_page, $base ); $response->link_header( 'prev', $prev_link ); } if ( $max_pages > $page ) { $next_page = $page + 1; $next_link = add_query_arg( 'page', $next_page, $base ); $response->link_header( 'next', $next_link ); } return $response; } protected function get_term( $id ) { $error = new WP_Error( 'rest_term_invalid', __( 'Term does not exist.' ), array( 'status' => 404 ) ); if ( ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) { return $error; } if ( (int) $id <= 0 ) { return $error; } $term = get_term( (int) $id, $this->taxonomy ); if ( empty( $term ) || $term->taxonomy !== $this->taxonomy ) { return $error; } return $term; } public function get_item_permissions_check( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } if ( 'edit' === $request['context'] && ! current_user_can( 'edit_term', $term->term_id ) ) { return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit this term.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function get_item( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } $response = $this->prepare_item_for_response( $term, $request ); return rest_ensure_response( $response ); } public function create_item_permissions_check( $request ) { if ( ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) { return false; } $taxonomy_obj = get_taxonomy( $this->taxonomy ); if ( ( is_taxonomy_hierarchical( $this->taxonomy ) && ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) || ( ! is_taxonomy_hierarchical( $this->taxonomy ) && ! current_user_can( $taxonomy_obj->cap->assign_terms ) ) ) { return new WP_Error( 'rest_cannot_create', __( 'Sorry, you are not allowed to create terms in this taxonomy.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function create_item( $request ) { if ( isset( $request['parent'] ) ) { if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) { return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Cannot set parent term, taxonomy is not hierarchical.' ), array( 'status' => 400 ) ); } $parent = get_term( (int) $request['parent'], $this->taxonomy ); if ( ! $parent ) { return new WP_Error( 'rest_term_invalid', __( 'Parent term does not exist.' ), array( 'status' => 400 ) ); } } $prepared_term = $this->prepare_item_for_database( $request ); $term = wp_insert_term( wp_slash( $prepared_term->name ), $this->taxonomy, wp_slash( (array) $prepared_term ) ); if ( is_wp_error( $term ) ) { $term_id = $term->get_error_data( 'term_exists' ); if ( $term_id ) { $existing_term = get_term( $term_id, $this->taxonomy ); $term->add_data( $existing_term->term_id, 'term_exists' ); $term->add_data( array( 'status' => 400, 'term_id' => $term_id, ) ); } return $term; } $term = get_term( $term['term_id'], $this->taxonomy ); do_action( "rest_insert_{$this->taxonomy}", $term, $request, true ); $schema = $this->get_item_schema(); if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) { $meta_update = $this->meta->update_value( $request['meta'], $term->term_id ); if ( is_wp_error( $meta_update ) ) { return $meta_update; } } $fields_update = $this->update_additional_fields_for_object( $term, $request ); if ( is_wp_error( $fields_update ) ) { return $fields_update; } $request->set_param( 'context', 'edit' ); do_action( "rest_after_insert_{$this->taxonomy}", $term, $request, true ); $response = $this->prepare_item_for_response( $term, $request ); $response = rest_ensure_response( $response ); $response->set_status( 201 ); $response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $term->term_id ) ); return $response; } public function update_item_permissions_check( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } if ( ! current_user_can( 'edit_term', $term->term_id ) ) { return new WP_Error( 'rest_cannot_update', __( 'Sorry, you are not allowed to edit this term.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function update_item( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } if ( isset( $request['parent'] ) ) { if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) { return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Cannot set parent term, taxonomy is not hierarchical.' ), array( 'status' => 400 ) ); } $parent = get_term( (int) $request['parent'], $this->taxonomy ); if ( ! $parent ) { return new WP_Error( 'rest_term_invalid', __( 'Parent term does not exist.' ), array( 'status' => 400 ) ); } } $prepared_term = $this->prepare_item_for_database( $request ); if ( ! empty( $prepared_term ) ) { $update = wp_update_term( $term->term_id, $term->taxonomy, wp_slash( (array) $prepared_term ) ); if ( is_wp_error( $update ) ) { return $update; } } $term = get_term( $term->term_id, $this->taxonomy ); do_action( "rest_insert_{$this->taxonomy}", $term, $request, false ); $schema = $this->get_item_schema(); if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) { $meta_update = $this->meta->update_value( $request['meta'], $term->term_id ); if ( is_wp_error( $meta_update ) ) { return $meta_update; } } $fields_update = $this->update_additional_fields_for_object( $term, $request ); if ( is_wp_error( $fields_update ) ) { return $fields_update; } $request->set_param( 'context', 'edit' ); do_action( "rest_after_insert_{$this->taxonomy}", $term, $request, false ); $response = $this->prepare_item_for_response( $term, $request ); return rest_ensure_response( $response ); } public function delete_item_permissions_check( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } if ( ! current_user_can( 'delete_term', $term->term_id ) ) { return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this term.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function delete_item( $request ) { $term = $this->get_term( $request['id'] ); if ( is_wp_error( $term ) ) { return $term; } $force = isset( $request['force'] ) ? (bool) $request['force'] : false; if ( ! $force ) { return new WP_Error( 'rest_trash_not_supported', sprintf( __( "Terms do not support trashing. Set '%s' to delete." ), 'force=true' ), array( 'status' => 501 ) ); } $request->set_param( 'context', 'view' ); $previous = $this->prepare_item_for_response( $term, $request ); $retval = wp_delete_term( $term->term_id, $term->taxonomy ); if ( ! $retval ) { return new WP_Error( 'rest_cannot_delete', __( 'The term cannot be deleted.' ), array( 'status' => 500 ) ); } $response = new WP_REST_Response(); $response->set_data( array( 'deleted' => true, 'previous' => $previous->get_data(), ) ); do_action( "rest_delete_{$this->taxonomy}", $term, $response, $request ); return $response; } public function prepare_item_for_database( $request ) { $prepared_term = new stdClass(); $schema = $this->get_item_schema(); if ( isset( $request['name'] ) && ! empty( $schema['properties']['name'] ) ) { $prepared_term->name = $request['name']; } if ( isset( $request['slug'] ) && ! empty( $schema['properties']['slug'] ) ) { $prepared_term->slug = $request['slug']; } if ( isset( $request['taxonomy'] ) && ! empty( $schema['properties']['taxonomy'] ) ) { $prepared_term->taxonomy = $request['taxonomy']; } if ( isset( $request['description'] ) && ! empty( $schema['properties']['description'] ) ) { $prepared_term->description = $request['description']; } if ( isset( $request['parent'] ) && ! empty( $schema['properties']['parent'] ) ) { $parent_term_id = 0; $requested_parent = (int) $request['parent']; if ( $requested_parent ) { $parent_term = get_term( $requested_parent, $this->taxonomy ); if ( $parent_term instanceof WP_Term ) { $parent_term_id = $parent_term->term_id; } } $prepared_term->parent = $parent_term_id; } return apply_filters( "rest_pre_insert_{$this->taxonomy}", $prepared_term, $request ); } public function prepare_item_for_response( $item, $request ) { $fields = $this->get_fields_for_response( $request ); $data = array(); if ( in_array( 'id', $fields, true ) ) { $data['id'] = (int) $item->term_id; } if ( in_array( 'count', $fields, true ) ) { $data['count'] = (int) $item->count; } if ( in_array( 'description', $fields, true ) ) { $data['description'] = $item->description; } if ( in_array( 'link', $fields, true ) ) { $data['link'] = get_term_link( $item ); } if ( in_array( 'name', $fields, true ) ) { $data['name'] = $item->name; } if ( in_array( 'slug', $fields, true ) ) { $data['slug'] = $item->slug; } if ( in_array( 'taxonomy', $fields, true ) ) { $data['taxonomy'] = $item->taxonomy; } if ( in_array( 'parent', $fields, true ) ) { $data['parent'] = (int) $item->parent; } if ( in_array( 'meta', $fields, true ) ) { $data['meta'] = $this->meta->get_value( $item->term_id, $request ); } $context = ! empty( $request['context'] ) ? $request['context'] : 'view'; $data = $this->add_additional_fields_to_object( $data, $request ); $data = $this->filter_response_by_context( $data, $context ); $response = rest_ensure_response( $data ); if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) { $response->add_links( $this->prepare_links( $item ) ); } return apply_filters( "rest_prepare_{$this->taxonomy}", $response, $item, $request ); } protected function prepare_links( $term ) { $links = array( 'self' => array( 'href' => rest_url( rest_get_route_for_term( $term ) ), ), 'collection' => array( 'href' => rest_url( rest_get_route_for_taxonomy_items( $this->taxonomy ) ), ), 'about' => array( 'href' => rest_url( sprintf( 'wp/v2/taxonomies/%s', $this->taxonomy ) ), ), ); if ( $term->parent ) { $parent_term = get_term( (int) $term->parent, $term->taxonomy ); if ( $parent_term ) { $links['up'] = array( 'href' => rest_url( rest_get_route_for_term( $parent_term ) ), 'embeddable' => true, ); } } $taxonomy_obj = get_taxonomy( $term->taxonomy ); if ( empty( $taxonomy_obj->object_type ) ) { return $links; } $post_type_links = array(); foreach ( $taxonomy_obj->object_type as $type ) { $rest_path = rest_get_route_for_post_type_items( $type ); if ( empty( $rest_path ) ) { continue; } $post_type_links[] = array( 'href' => add_query_arg( $this->rest_base, $term->term_id, rest_url( $rest_path ) ), ); } if ( ! empty( $post_type_links ) ) { $links['https://api.w.org/post_type'] = $post_type_links; } return $links; } public function get_item_schema() { if ( $this->schema ) { return $this->add_additional_fields_schema( $this->schema ); } $schema = array( '$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'post_tag' === $this->taxonomy ? 'tag' : $this->taxonomy, 'type' => 'object', 'properties' => array( 'id' => array( 'description' => __( 'Unique identifier for the term.' ), 'type' => 'integer', 'context' => array( 'view', 'embed', 'edit' ), 'readonly' => true, ), 'count' => array( 'description' => __( 'Number of published posts for the term.' ), 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true, ), 'description' => array( 'description' => __( 'HTML description of the term.' ), 'type' => 'string', 'context' => array( 'view', 'edit' ), ), 'link' => array( 'description' => __( 'URL of the term.' ), 'type' => 'string', 'format' => 'uri', 'context' => array( 'view', 'embed', 'edit' ), 'readonly' => true, ), 'name' => array( 'description' => __( 'HTML title for the term.' ), 'type' => 'string', 'context' => array( 'view', 'embed', 'edit' ), 'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field', ), 'required' => true, ), 'slug' => array( 'description' => __( 'An alphanumeric identifier for the term unique to its type.' ), 'type' => 'string', 'context' => array( 'view', 'embed', 'edit' ), 'arg_options' => array( 'sanitize_callback' => array( $this, 'sanitize_slug' ), ), ), 'taxonomy' => array( 'description' => __( 'Type attribution for the term.' ), 'type' => 'string', 'enum' => array( $this->taxonomy ), 'context' => array( 'view', 'embed', 'edit' ), 'readonly' => true, ), ), ); $taxonomy = get_taxonomy( $this->taxonomy ); if ( $taxonomy->hierarchical ) { $schema['properties']['parent'] = array( 'description' => __( 'The parent term ID.' ), 'type' => 'integer', 'context' => array( 'view', 'edit' ), ); } $schema['properties']['meta'] = $this->meta->get_field_schema(); $this->schema = $schema; return $this->add_additional_fields_schema( $this->schema ); } public function get_collection_params() { $query_params = parent::get_collection_params(); $taxonomy = get_taxonomy( $this->taxonomy ); $query_params['context']['default'] = 'view'; $query_params['exclude'] = array( 'description' => __( 'Ensure result set excludes specific IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), 'default' => array(), ); $query_params['include'] = array( 'description' => __( 'Limit result set to specific IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), 'default' => array(), ); if ( ! $taxonomy->hierarchical ) { $query_params['offset'] = array( 'description' => __( 'Offset the result set by a specific number of items.' ), 'type' => 'integer', ); } $query_params['order'] = array( 'description' => __( 'Order sort attribute ascending or descending.' ), 'type' => 'string', 'default' => 'asc', 'enum' => array( 'asc', 'desc', ), ); $query_params['orderby'] = array( 'description' => __( 'Sort collection by term attribute.' ), 'type' => 'string', 'default' => 'name', 'enum' => array( 'id', 'include', 'name', 'slug', 'include_slugs', 'term_group', 'description', 'count', ), ); $query_params['hide_empty'] = array( 'description' => __( 'Whether to hide terms not assigned to any posts.' ), 'type' => 'boolean', 'default' => false, ); if ( $taxonomy->hierarchical ) { $query_params['parent'] = array( 'description' => __( 'Limit result set to terms assigned to a specific parent.' ), 'type' => 'integer', ); } $query_params['post'] = array( 'description' => __( 'Limit result set to terms assigned to a specific post.' ), 'type' => 'integer', 'default' => null, ); $query_params['slug'] = array( 'description' => __( 'Limit result set to terms with one or more specific slugs.' ), 'type' => 'array', 'items' => array( 'type' => 'string', ), ); return apply_filters( "rest_{$this->taxonomy}_collection_params", $query_params, $taxonomy ); } protected function check_is_taxonomy_allowed( $taxonomy ) { $taxonomy_obj = get_taxonomy( $taxonomy ); if ( $taxonomy_obj && ! empty( $taxonomy_obj->show_in_rest ) ) { return true; } return false; } } 