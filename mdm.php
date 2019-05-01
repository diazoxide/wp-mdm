<?php

/*
Plugin Name: WP Mark Deleted Media
Plugin URI: http://github.com/diazoxide/wp_mdm
Description: This plugin finds deleted media before post save
Version: 1.0
Author: Aaron Yordanyan
Author URI: https://www.linkedin.com/in/aaron-yor/
License: GPL2
*/

/**
 * Adding filter @wp_insert_post_data
 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/wp_insert_post_data
 * */
add_filter('wp_insert_post_data', 'filter_post_data', '99', 2);


/**
 * Getting media id from class string
 * @param $str
 * @return null
 */
function getMediaId($str)
{
    preg_match('/wp-image-(\d+)/', $str, $match, PREG_OFFSET_CAPTURE, 0);
    return isset($match[1][0]) ? $match[1][0] : null;
}


/**
 * Getting size string from class string
 * @param $str
 * @return null
 */
function getSize($str)
{
    preg_match('/size-(\w+)/', $str, $match, PREG_OFFSET_CAPTURE, 0);
    return isset($match[1][0]) ? $match[1][0] : null;
}

/**
 * Return class attribute value from string
 * @param $str
 * @return null
 */
function getClass($str)
{
    preg_match('/class=\\\\"(.*?)\\\\"/', $str, $match, PREG_OFFSET_CAPTURE, 0);
    return isset($match[1][0]) ? $match[1][0] : null;
}


/**
 * Return src attribute value from string
 * @param $str
 * @return null
 */
function getSrc($str)
{
    preg_match('/src=\\\\"(.*?)\\\\"/', $str, $match, PREG_OFFSET_CAPTURE, 0);
    return isset($match[1][0]) ? $match[1][0] : null;
}


/**
 * Checking if image exists
 * For first loading image headers
 * Than checking status and content type
 * If status is ok and content type contains "image/" suffix than return true
 * @param $url
 * @return boolean
 */
function imageExists($url)
{

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    /*
     * Don't download content
     * */
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    /*
     * Ignore ssl verification
     * */
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($result !== false && strpos($contentType, 'image') !== false) {
        return true;
    } else {
        return false;
    }
}

/*
 * Calling filter post_data to change post content before update or publish
 * */
function filter_post_data($data, $postarr)
{

    /**
     * Making regex replacement
     * Getting all images tags without any grouping
     * Than getting class and src attributes for each image
     * Getting media id from @class and checking if attachment exists in db
     * If attachment not exists than checking if remote @src url not exists
     * Than if both conditions is false adding @deleted class to @img tag
     * */
    $data['post_content'] = preg_replace_callback(
    /*
     * Simple pattern for finding <img ...> tags in string
     * */
        '/\<img.*?\>/',
        function ($match) {

            /**
             * Taking full match as $image variable
             * */
            $image = $match[0];

            /*
             * Extracting class attribute from image string
             * */
            $class = getClass($image);

            /*
             * Getting media id
             * */
            $mediaId = getMediaId($class);

            /*
             * Getting size
             * */
            $size = getSize($class);

            /*
             * Loading attachment post from database
             * */
            $attachment = get_post($mediaId);

            /**
             * Checking conditions for adding @deleted class
             * */
            if (
                (
                    /*
                     * If attachment post not exists
                     * */
                    !$attachment

                    /**
                     * Or attachment type not @attachment
                     * */
                    || $attachment->post_type != "attachment"
                )
                /*
                 * And image file not exists
                 * */
                && !imageExists(getSrc($image))
            ) {

                /*
                 * Adding @deleted class
                 * For first using regular expression replacement with callback function
                 * */
                $image = preg_replace_callback(

                /*
                 * Simple pattern for extracting class="..." attribute from string
                 * */
                    '/class=\\\\"(.*?)\\\\"/',
                    function ($match) {

                        /**
                         * Taking first group of match
                         * Its like "class1 class2 class3"
                         * Than appending our new @deleted class
                         * And returning new full @class attribute
                         * */
                        $class = $match[1];
                        return 'class="' . $class . ' deleted"';
                    },
                    $image
                );

            }

            /*
             * Appending after src value anchor tag
             * F.e. http://.../my.jpg#28-medium
             * */
            if ($mediaId && $size) {
                $image = preg_replace_callback(
                /*
                 * Simple pattern for extracting src="..." attribute from string
                 * */
                    '/src=\\\\"(.*?)\\\\"/',
                    function ($match) use ($mediaId, $size) {
                        $src = $match[1];
                        return 'src="' . $src . '#' . $mediaId . '-' . $size . '"';
                    },
                    $image
                );
            }

            /*
             * Returning final image string with changes
             * */
            return $image;

        },
        $data['post_content']
    );

    return $data;
}
