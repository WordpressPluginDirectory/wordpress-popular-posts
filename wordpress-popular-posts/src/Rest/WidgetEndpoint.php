<?php
namespace WordPressPopularPosts\Rest;

use WordPressPopularPosts\{ Output, Translate };
use WordPressPopularPosts\Traits\QueriesPosts;

class WidgetEndpoint extends Endpoint {

    use QueriesPosts;

    /**
     * Output object.
     *
     * @var     \WordPressPopularPosts\Output       $output
     * @access  private
     */
    protected $output;

    /**
     * Initializes class.
     *
     * @param   array
     * @param   \WordPressPopularPosts\Translate
     * @param   \WordPressPopularPosts\Output
     */
    public function __construct(array $config, Translate $translate, Output $output)
    {
        $this->config = $config;
        $this->translate = $translate;
        $this->output = $output;
    }

    /**
     * Registers the endpoint(s).
     *
     * @since   5.3.0
     */
    public function register()
    {
        /** @TODO: Remove V1 endpoint after deleting all code related to the classic widget */
        $version = '1';
        $namespace = 'wordpress-popular-posts/v' . $version;

        register_rest_route($namespace, '/popular-posts/widget/(?P<id>[\d]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_widget'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_widget_params(),
            ]
        ]);

        $version = '2';
        $namespace = 'wordpress-popular-posts/v' . $version;

        register_rest_route($namespace, '/widget/', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'get_widget_block'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_widget_params(),
            ]
        ]);
    }

    /**
     * Retrieves a popular posts widget for display.
     *
     * @since 4.1.0
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_Error|\WP_REST_Response Response object on success, or WP_Error object on failure.
     */
    public function get_widget($request)
    {
        $instance_id = $request->get_param('id');
        $is_single = $request->get_param('is_single');
        $lang = $request->get_param('lang');
        $widget = get_option('widget_wpp');

        $data = $this->prepare_widget_item_for_response($instance_id, $is_single, $lang, $widget, $request);

        if ( $data ) {
            return new \WP_REST_Response($data, 200);
        }

        return new \WP_Error('invalid_instance', __('Invalid Widget Instance ID', 'wordpress-popular-posts'));
    }

    /**
     * Prepares widget instance for response.
     *
     * @since   5.0.0
     * @param   int
     * @param   int
     * @param   string
     * @param   array
     * @param  \WP_REST_Request
     * @return  array|boolean
     */
    public function prepare_widget_item_for_response($instance_id, $is_single, $lang, $widget, $request)
    {
        // Valid instance
        if ( $widget && isset($widget[$instance_id]) ) {

            $notice = '';

            if ( is_user_logged_in() && current_user_can('manage_options') ) {
                ob_start();
                ?>
                <style>
                    .wpp-notice {
                        margin: 0 0 22px;
                        padding: 18px 22px;
                        background: #fcfcf7;
                        border: #ffff63 4px solid;
                    }

                        .wpp-notice p:nth-child(2n) {
                            margin: 0;
                            font-size: 0.85em;
                        }
                </style>
                <div class="wpp-notice">
                    <p><strong>Important notice for administrators:</strong> The WordPress Popular Posts "classic" widget has been discontinued.</p>
                    <p>This widget has been discontinued as of version 7.0. Please switch to either the WordPress Popular Posts block or the wpp shortcode.</p>
                </div>
                <?php
                $notice = ob_get_clean() . "\n";
            }

            return [
                'widget' => ( $this->config['tools']['cache']['active'] ? '<!-- cached -->' : '' ) . $notice
            ];
        }

        return false;
    }

    /**
     * Retrieves a popular posts widget for display.
     *
     * @since 5.4.0
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_Error|\WP_REST_Response Response object on success, or WP_Error object on failure.
     */
    public function get_widget_block($request)
    {
        $instance = $request->get_params();

        $is_single = $request->get_param('is_single');
        $lang = $request->get_param('lang');

        // Multilang support
        $this->set_lang($lang);

        $popular_posts = $this->maybe_query($instance);

        if ( is_numeric($is_single) && $is_single > 0 ) {
            add_filter('wpp_is_single', function($id) use ($is_single) {
                return $is_single;
            });
        }

        $this->output->set_data($popular_posts->get_posts());
        $this->output->set_public_options($instance);
        $this->output->build_output();

        return [
            'widget' => ( $this->config['tools']['cache']['active'] ? '<!-- cached -->' : '' ) . $this->output->get_output()
        ];
    }

    /**
     * Retrieves the query params for getting a widget instance.
     *
     * @since 4.1.0
     *
     * @return array Query parameters for getting a widget instance.
     */
    public function get_widget_params()
    {
        return [
            'is_single' => [
                'type' => 'integer',
                'default' => null,
                'sanitize_callback' => 'absint'
            ],
            'lang' => [
                'type' => 'string',
                'default' => null,
                'sanitize_callback' => 'sanitize_text_field'
            ],
        ];
    }
}
