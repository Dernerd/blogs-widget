<?php
/*
Plugin Name: Blogs Widget
Plugin URI: http://premium.wpmudev.org/project/blogs-widget/
Description: Show recently updated blogs across your site, with avatars, through this handy widget
Author: PSOURCE
Version: 1.0.9.5
Author URI: https://github.com/cp-psource
Network: true
Text Domain: widget_blogs
*/

/*
Copyright 2014-2024 PSOURCE (https://github.com/cp-psource)
Author - S H Mohanjith
Contributors - Andrew Billits

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('init', 'widget_blogs_init');

function widget_blogs_init(): void
{
    if (!is_multisite()) {
        exit('The Widget Blogs plugin is only compatible with WordPress Multisite.');
    }
    load_plugin_textdomain('widget_blogs', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('widgets_init', 'widget_blogs_widget_init');

function widget_blogs_widget_init(): void
{
    register_widget(BlogsWidget::class);
}

class BlogsWidget extends WP_Widget
{
    private string $translation_domain = 'widget_blogs';

    public function __construct()
    {
        $widget_ops = ['description' => __('Display Blogs Pages', $this->translation_domain)];
        parent::__construct(
            'blogs_widget',
            __('Blogs', $this->translation_domain),
            $widget_ops
        );
    }

    public function widget($args, $instance): void
    {
        global $wpdb;

        $args = wp_parse_args($args);
        $instance = wp_parse_args((array)$instance, $this->default_instance());

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];

        $public_where = $instance['public-only'] === 'yes' ? 'AND public = 1' : '';
        $template_where = '';
        if (class_exists('blog_templates') && $instance['templates'] === 'no') {
            $template_blogs = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM {$wpdb->base_prefix}nbt_templates WHERE network_id = %d", $wpdb->siteid));
            if (!empty($template_blogs)) {
                $template_where = ' AND blog_id NOT IN (' . implode(',', $template_blogs) . ')';
            }
        }

        $order_by = match ($instance['order']) {
            'most_recent' => 'registered DESC',
            default => 'RAND()',
        };

        $query = $wpdb->prepare(
            "SELECT blog_id FROM {$wpdb->base_prefix}blogs WHERE site_id = %d AND spam != '1' AND archived != '1' AND deleted != '1' {$public_where} {$template_where} ORDER BY {$order_by} LIMIT %d",
            $wpdb->siteid,
            (int)$instance['number']
        );

        $blogs = $wpdb->get_results($query, ARRAY_A);

        if ($blogs) {
            echo '<ul>';
            foreach ($blogs as $blog) {
                $blog_details = get_blog_details($blog['blog_id']);
                if (!$blog_details) {
                    continue;
                }
                $blog_name = esc_html(mb_substr($blog_details->blogname, 0, $instance['blog-name-characters']));
                $site_url = esc_url($blog_details->siteurl);

                echo '<li>';
                if ($instance['display'] === 'avatar_blog_name' && function_exists('get_blog_avatar')) {
                    echo '<a href="' . $site_url . '">' . get_blog_avatar($blog['blog_id'], $instance['avatar-size']) . '</a> ';
                }
                if ($instance['display'] === 'avatar' && function_exists('get_blog_avatar')) {
                    echo '<a href="' . $site_url . '">' . get_blog_avatar($blog['blog_id'], $instance['avatar-size']) . '</a>';
                } else {
                    echo '<a href="' . $site_url . '">' . $blog_name . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance): array
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['display'] = sanitize_text_field($new_instance['display']);
        $instance['blog-name-characters'] = (int)$new_instance['blog-name-characters'];
        $instance['public-only'] = sanitize_text_field($new_instance['public-only']);
        $instance['templates'] = sanitize_text_field($new_instance['templates']);
        $instance['order'] = sanitize_text_field($new_instance['order']);
        $instance['number'] = (int)$new_instance['number'];
        $instance['avatar-size'] = (int)$new_instance['avatar-size'];

        return $instance;
    }

    public function form($instance): void
    {
        $instance = wp_parse_args((array)$instance, $this->default_instance());
        ?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'widget_blogs'); ?>:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($instance['title']); ?>"/>
        </p>
        <?php if (function_exists('get_blog_avatar')) : ?>
            <p>
                <label for="<?php echo $this->get_field_id('display'); ?>"><?php _e('Display', 'widget_blogs'); ?>:</label>
                <select name="<?php echo $this->get_field_name('display'); ?>" id="<?php echo $this->get_field_id('display'); ?>" class="widefat">
                    <option value="avatar_blog_name" <?php selected($instance['display'], 'avatar_blog_name'); ?>><?php _e('Avatar + Blog Name', 'widget_blogs'); ?></option>
                    <option value="avatar" <?php selected($instance['display'], 'avatar'); ?>><?php _e('Avatar Only', 'widget_blogs'); ?></option>
                    <option value="blog_name" <?php selected($instance['display'], 'blog_name'); ?>><?php _e('Blog Name Only', 'widget_blogs'); ?></option>
                </select>
            </p>
        <?php endif; ?>
        <p>
            <label for="<?php echo $this->get_field_id('blog-name-characters'); ?>"><?php _e('Blog Name Characters', 'widget_blogs'); ?>:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('blog-name-characters'); ?>"
                   name="<?php echo $this->get_field_name('blog-name-characters'); ?>" type="number" min="1" max="500"
                   value="<?php echo esc_attr($instance['blog-name-characters']); ?>"/>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('public-only'); ?>"><?php _e('Public Only', 'widget_blogs'); ?>:</label>
            <select name="<?php echo $this->get_field_name('public-only'); ?>" id="<?php echo $this->get_field_id('public-only'); ?>" class="widefat">
                <option value="yes" <?php selected($instance['public-only'], 'yes'); ?>><?php _e('Yes', 'widget_blogs'); ?></option>
                <option value="no" <?php selected($instance['public-only'], 'no'); ?>><?php _e('No', 'widget_blogs'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('templates'); ?>"><?php _e('Hide Template Blogs', 'widget_blogs'); ?>:</label>
            <select name="<?php echo $this->get_field_name('templates'); ?>" id="<?php echo $this->get_field_id('templates'); ?>" class="widefat">
                <option value="no" <?php selected($instance['templates'], 'no'); ?>><?php _e('Yes', 'widget_blogs'); ?></option>
                <option value="yes" <?php selected($instance['templates'], 'yes'); ?>><?php _e('No', 'widget_blogs'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('order'); ?>"><?php _e('Order', 'widget_blogs'); ?>:</label>
            <select name="<?php echo $this->get_field_name('order'); ?>" id="<?php echo $this->get_field_id('order'); ?>" class="widefat">
                <option value="random" <?php selected($instance['order'], 'random'); ?>><?php _e('Random', 'widget_blogs'); ?></option>
                <option value="most_recent" <?php selected($instance['order'], 'most_recent'); ?>><?php _e('Most Recent', 'widget_blogs'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of Blogs', 'widget_blogs'); ?>:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('number'); ?>"
                   name="<?php echo $this->get_field_name('number'); ?>" type="number" min="1" max="50"
                   value="<?php echo esc_attr($instance['number']); ?>"/>
        </p>
        <?php if (function_exists('get_blog_avatar')) : ?>
            <p>
                <label for="<?php echo $this->get_field_id('avatar-size'); ?>"><?php _e('Avatar Size', 'widget_blogs'); ?>:</label>
                <input class="widefat" id="<?php echo $this->get_field_id('avatar-size'); ?>"
                       name="<?php echo $this->get_field_name('avatar-size'); ?>" type="number" min="1" max="100"
                       value="<?php echo esc_attr($instance['avatar-size']); ?>"/>
            </p>
        <?php endif;
    }

    private function default_instance(): array
    {
        return [
            'title' => __('Blogs', $this->translation_domain),
            'display' => 'avatar_blog_name',
            'blog-name-characters' => 40,
            'public-only' => 'yes',
            'templates' => 'no',
            'order' => 'most_recent',
            'number' => 10,
            'avatar-size' => 50
        ];
    }
}
?>

