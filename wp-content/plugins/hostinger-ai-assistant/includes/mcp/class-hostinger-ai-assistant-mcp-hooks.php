<?php

class Hostinger_Ai_Assistant_Mcp_Hooks {
    public function init(): void {
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_meta_fields' ), 10, 3 );
    }

    public function filter_product_meta_fields( $response, $post, $request ): WP_REST_Response {
        if ( isset( $response->data['meta_data'] ) && is_array( $response->data['meta_data'] ) ) {
            $response->data['meta_data'] = array_values(
                array_filter(
                    $response->data['meta_data'],
                    function ( $meta ) {
                        return ! str_starts_with( $meta->key, '_uag' );
                    }
                )
            );
        }

        return $response;
    }
}
