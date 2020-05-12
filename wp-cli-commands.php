<?php

/**
 * Convert ACF fields to Gutenberg blocks
 */
class cli_convert_acf_to_gutenberg extends WP_CLI_Command {
    /**
     * Analyse posts for use of Advanced Custom Fields.
     * ## OPTIONS
     *
     * [--post_id]
     * : Only analyse a post with this post id.
     *
     * [--post_type]
     * : Only analyse posts of this post type.
     *
     * [--numberposts]
     * : Total number of posts to retrieve.
     *
     * [--show_ids]
     * : Show id's of posts that contain a content block.
     */
    function analyse($args, $assoc_args) {
        $get_posts_args = [
            'numberposts' => (isset($assoc_args['numberposts']) ? $assoc_args['numberposts'] : 5),
            'post_type' => (isset($assoc_args['post_type']) ? $assoc_args['post_type'] : 'post'),
            'include' => (isset($assoc_args['post_id']) && intval($assoc_args['post_id']))
            ? [ intval($assoc_args['post_id'])]
            : [],
        ];

        $posts = get_posts($get_posts_args);

        $acf_fc_layout = [
            'measurement_table_block' => ['post_ids' => []],
            'box_block'               => ['post_ids' => []],
            'post_list_block'         => ['post_ids' => []],
            'text_block'              => ['post_ids' => []],
            'image_block'             => ['post_ids' => []],
            'image_full_width_block'  => ['post_ids' => []],
            'image_and_text_block'    => ['post_ids' => []],
            'video_block'             => ['post_ids' => []],
        ];

        foreach ($posts as $post) {
            $content_blocks = $this->get_blocks($post->ID);

            if (!$content_blocks) {
                continue;
            }

            $unique_blocks = array_unique(array_map(function ($block) {
                return $block['acf_fc_layout'];
            }, $content_blocks));

            foreach ($unique_blocks as $block) {
                $acf_fc_layout[$block]['post_ids'][] = $post->ID;
            }
        }

        foreach ($acf_fc_layout as $key => $value) {
            WP_CLI::log($key . ': ' . count($value['post_ids']));
            if (isset($assoc_args['show_ids'])) {
                WP_CLI::log(implode(', ', $value['post_ids']));
            }
        }
    }

    /**
     * Converts ACF fields in posts to Gutenberg blocks
     *
     * @todo: https://www.billerickson.net/access-gutenberg-block-data/
     * ## OPTIONS
     *
     * [--post_id]
     * : Only for this post id.
     *
     * [--post_type]
     * : Only analyse posts of this post type.
     *
     * [--numberposts]
     * : Total number of posts to retrieve.
     */
    function convert($args, $assoc_args) {
        $post_id = (isset($assoc_args['post_id']) ? intval($assoc_args['post_id']) : null);

        // Convert single post
        if (isset($post_id) && is_int($post_id)) {
            return $this->convert_post($post_id);
        }

        // Convert all posts
        $get_posts_args = [
            'numberposts' => (isset($assoc_args['numberposts']) ? $assoc_args['numberposts'] : 5),
            'post_type' => (isset($assoc_args['post_type']) ? $assoc_args['post_type'] : 'post'),
        ];

        $posts = get_posts($get_posts_args);

        foreach ($posts as $post) {
            $this->convert_post($post->ID);
        }
    }

    private function convert_post($post_id) {
        $blocks = $this->get_blocks($post_id);

        $post_content = array_map(function ($block) {
            $instance_method = "convert_$block[acf_fc_layout]";
            return $this->$instance_method($block);
        }, $blocks);

        $post_id = wp_update_post([
            'ID' => $post_id,
            'post_content' => implode($post_content),
        ], true);

        $this->handle_error($post_id);
    }

    private function convert_image_full_width_block($block) {
        return $this->get_serialized_image_block($block['image'], 'wide');
    }

    private function convert_video_block($block) {
        return 'video block';
    }

    private function convert_post_list_block($block) {
        return 'post list block';
    }

    private function convert_image_and_text_block($block) {
        return 'image and text block';
    }

    private function convert_measurement_table_block($block) {
        return 'measurement table block';
    }

    private function convert_box_block($block) {
        return 'box block';
    }

    private function convert_text_block($block) {
        $block_content = '';

        if ($block['title']) {
            $block_content .= serialize_block([
                'blockName' => 'core/heading',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => "<h2>$block[title]</h2>",
                'innerContent' => ["<h2>$block[title]</h2>"],
            ]);
        }

        if ($block['text']) {
            $block_content .= serialize_block([
                'blockName' => 'core/paragraph',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => $block['text'],
                'innerContent' => [$block['text']],
            ]);
        }

        return $block_content;
    }

    private function convert_image_block($block) {
        $block_content = '';
        $image_count = count($block['image_set']);

        if ($image_count == 1) {
            $block_content .= $this->get_serialized_image_block($block['image_set'][0]['image']);
        }

        if ($image_count > 1) {
            $block_content .= $this->get_serialized_gallery_block(array_map(function ($image) {
                return $image['image'];
            }, $block['image_set']));
        }

        return $block_content;
    }

    private function get_serialized_gallery_block($image_ids) {
        $image_count = count($image_ids);

        $gallery_html = sprintf(
            '<figure class="wp-block-gallery columns-%s is-cropped"><ul class="blocks-gallery-grid">',
            ($image_count > 3) ? '3' : $image_count
        );

        foreach ($image_ids as $image_id) {
            $image = $this->get_image($image_id);
            $image_html = sprintf(
                '<li class="blocks-gallery-item"><figure><img src="%2$s" alt="%5$s" data-id="%1$s" data-full-url="%3$s" data-link="%4$s" class="wp-image-%1$s"/>%6$s</figure></li>',
                $image_id,
                $image['src_large'],
                $image['src_full'],
                $image['link'],
                $image['alt'],
                ($image['caption']) ? "<figcaption class='blocks-gallery-item__caption'>$image[caption]</figcaption>" : ''
            );
            $gallery_html .= $image_html;
        }

        $gallery_html .= '</ul></figure>';

        return serialize_block([
            'blockName' => 'core/gallery',
            'attrs' => [
                'ids' => $image_ids,
            ],
            'innerBlocks' => [],
            'innerHTML' => $gallery_html,
            'innerContent' => [$gallery_html],
        ]);
    }

    private function get_serialized_image_block($image_id, $align = false) {
        $image_html = $this->get_image_html($image_id, $align);

        $block = [
            'blockName' => 'core/image',
            'attrs' => [
                'id' => $image_id,
                'sizeSlug' => 'large',
            ],
            'innerBlocks' => [],
            'innerHTML' => $image_html,
            'innerContent' => [$image_html],
        ];

        if ($align) {
            $block['attrs']['align'] = $align;
        }

        return serialize_block($block);
    }

    private function get_image($image_id) {
        $image = [
            'src_large' => (wp_get_attachment_image_src($image_id, 'large')[0]) ?: '',
            'src_full' => (wp_get_attachment_image_src($image_id, 'full')[0]) ?: '',
            'alt' => (get_post_meta($image_id, '_wp_attachment_image_alt', true)) ?: '',
            'caption' => (wp_get_attachment_caption($image_id)) ?: '',
            'link' => (get_permalink($image_id)) ?: '',
        ];

        return $image;
    }

    private function get_image_html($image_id, $align = false) {
        $image = $this->get_image($image_id);

        return sprintf(
            '<figure class="wp-block-image %s size-large"><img src="%s" alt="%s" class="wp-image-%s"/>%s</figure>',
            ($align) ? 'alignwide' : '',
            $image['src_large'],
            $image['alt'],
            $image_id,
            ($image['caption']) ? "<figcaption>$image[caption]</figcaption>" : ''
        );
    }

    private function get_blocks($post_id) {
        return get_fields($post_id)['content_blocks'];
    }

    private function handle_error($post_id) {
        if (is_wp_error($post_id)) {
            $errors = $post_id->get_error_messages();

            foreach ($errors as $error) {
                WP_CLI::log($error);
            }
        }
    }
}

WP_CLI::add_command('acf2g', 'cli_convert_acf_to_gutenberg');
