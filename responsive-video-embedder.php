<?php

/**
 * 
 * Plugin Name: Responsive Video Embedder
 * Plugin URI: http://www.esiteq.com/projects/responsive-video-embedder/
 * Description: Simple yet powerful plugin to insert responsive videos
 * Author: Alex Raven
 * Version: 0.1
 * Author URI: http://www.esiteq.com/
 * 
 */

class Responsive_Insert_Video
{
    var $INSERT_SHORTCODE = 'rem_video';
    var $VIDEO_EXAMPLE = 'Example: https://www.youtube.com/watch?v=mcixldqDIEQ';
    var $HIDE_THIS_LIST = 'Hide this list';
    var $SHOW_THIS_LIST = 'Show this list';
    var $RECENT_COUNT = 20;
    function __construct()
    {
        //$screen = get_current_screen();
        $screen = new StdClass;
        add_thickbox();
        add_action( 'wp_head', array($this, 'head_scripts') );
        add_shortcode( $this->INSERT_SHORTCODE, array($this, 'video_shortcode') );
        if (stristr($_SERVER['PHP_SELF'], 'post.php') || (stristr($_SERVER['PHP_SELF'], 'post-new.php')))
        {
            add_action( 'admin_print_footer_scripts', array($this, 'insert_video_button') );
        }
        add_action( 'wp_ajax_rem_insert_video', array($this, 'rem_insert_video') );
        add_action( 'wp_ajax_nopriv_rem_insert_video', array($this, 'rem_insert_video') );
        add_action( 'init', array($this, 'enqueue_scripts') );
    }
    // ajax function
    function rem_insert_video()
    {
        global $wpdb;
        if ($_GET['subaction'])
        {
            $json = array();
            $recent = get_option('rem_insert_video_recent');
            if ($_GET['subaction'] == 'toggle-recent-list')
            {
                $show = (get_option('rem_insert_video_show_recent') === FALSE) ? 0 : get_option('rem_insert_video_show_recent');
                $show = ($show == 1) ? 0 : 1;
                $json['show'] = $show;
                update_option('rem_insert_video_show_recent', $show);
            }
            if ($_GET['subaction'] == 'remove-recent-videos')
            {
                unset($recent[$_GET['v']]);
                update_option('rem_insert_video_recent', $recent);
            }
            if ($_GET['subaction'] == 'insert-recent-videos')
            {
                $json = $recent[$_GET['v']];
                $json['start_time'] = gmdate('i:s', $json['start']);
            }
            if ($_GET['subaction'] == 'get-recent-videos')
            {
                $json['recent'] = array();
                if (is_array($recent))
                {
                    $times = array();
                    foreach ($recent as $row)
                    {
                        $json['recent'][] = $row;
                        $times[] = $row['time'];
                    }
                    array_multisort($times, SORT_DESC, $json['recent']);
                }
                else
                {
                    $json['recent'] = array();
                }
                array_splice($json['recent'], $this->RECENT_COUNT);
            }
            echo json_encode($json);
            die();
        }
        if (!$this->validate_url($_GET['vid_url']))
        {
            echo json_encode(array('errmsg'=>'Invalid video URL specified'));
            die();
        }
        parse_str( parse_url($_GET['vid_url'], PHP_URL_QUERY), $param );
        $json['v'] = $param['v'];
        $json['list'] = $param['list'];
        $json['autoplay'] = intval($_GET['vid_autoplay']);
        $json['autohide'] = intval($_GET['vid_autohide']);
        $json['loop'] = intval($_GET['vid_loop']);
        $json['controls'] = intval($_GET['vid_controls']);
        $json['time'] = time();
        if (strlen($_GET['vid_start'])>0)
        {
            $tmp = explode(':', $_GET['vid_start']);
            if (is_array($tmp))
            {
                if (count($tmp) == 1)
                {
                    $json['start'] = intval($tmp[0]);
                }
                elseif (count($tmp) == 2)
                {
                    $json['start'] = intval($tmp[0]) * 60 + intval($tmp[1]);
                }
                else
                {
                    $json['start'] = 0;
                }
                
            }
            else
            {
                $json['start'] = 0;
            }
        }
        else
        {
            $json['start'] = 0;
        }
        update_option('rem_insert_video', $json);
        $recent = get_option('rem_insert_video_recent');
        if ($recent === FALSE)
        {
            $recent = array();
        }
        $recent[$json['v']] = $json;
        update_option('rem_insert_video_recent', $recent); 
        echo json_encode($json);
        die();
    }
    //
    function head_scripts()
    {
        //
    }
    // embed video
    function validate_url($url)
    {
        $rx = '~
        ^(?:https?://)?
        (?:www\.)?
        (?:youtube\.com|youtu\.be)
        /watch\?v=([^&]+)
        ~x';
        return preg_match($rx, $url, $matches);
    }
    //
    function video_shortcode($param)
    {
        $html = '';
        $proto = is_ssl() ? 'https' : 'http';
        if (empty($param['id']) || strlen($param['id'])==0)
        {
            $html = '<h3 class="insert-video-error">rem_video: video id is not specified</h3>';
            return $html;
        }
        $autoplay = isset($param['autoplay']) ? intval($param['autoplay']) : 0;
        $controls = isset($param['controls']) ? intval($param['controls']) : 1;
        $loop     = isset($param['loop'])     ? intval($param['loop']) : 0;
        $autohide = isset($param['autohide']) ? intval($param['autohide']) : 1;
        $list     = isset($param['list'])     ? '&list='. $param['list'] : '';
        $html .= '<div class="rem-video-container">';
        $html .= '<iframe src="'. $proto. '://www.youtube.com/embed/'. $param['id']. '?autoplay='. $autoplay. '&controls='. $controls. '&loop='. $loop. '&autohide='. $autohide. $list. '" frameborder="0" width="100%" height="315"></iframe>';
        $html .= '</div>';
        return $html;
    }
    //
    function checkbox($name, $title, $value)
    {
        $checked = ($value == 1) ? ' checked="checked"' : '';
        echo '<label for="', $name, '" class="insert-video-checkbox">';
        echo '<input name="', $name, '" id="', $name, '" type="checkbox" value="1"', $checked, ' /> ', $title;
        echo '</label>';
    }
    //
    function enqueue_scripts()
    {
        wp_enqueue_style( 'responsive-video-embedder', plugins_url( '/css/responsive-video-embedder.css', __file__ ), false, '4.9.9' );
    }
    // add video button
    function insert_video_button()
    {
        $param = get_option('rem_insert_video');
?>
<div id="add-video-modal" style="display:none;">
    <form id="add-video-form" method="get">
    <input type="hidden" name="action" value="rem_insert_video" />
    <table class="insert-video-table">
        <tr>
            <th class="insert-video-title">Video URL:</th>
            <td><input type="text" class="insert-video-input" name="vid_url" id="vid_url" value="" placeholder="Paste video link here" /></td>
        </tr>
        <tr class="insert-video-status-row">
            <th>&nbsp;</th>
            <td><span id="insert-video-status"><?php echo $this->VIDEO_EXAMPLE; ?></span></td>
        </tr>
        <tr>
            <th>Parameters:</th>
            <td>
<?php
    $this->checkbox('vid_controls', 'Controls', $param['controls']);
    $this->checkbox('vid_autoplay', 'Autoplay', $param['autoplay']);
    $this->checkbox('vid_loop',     'Loop', $param['loop']);
    $this->checkbox('vid_autohide', 'Autohide', $param['autohide']);
    $start = gmdate('i:s', $param['start']);
?>
<label for="vid_start" class="insert-video-checkbox">Start:  
<input name="vid_start" id="vid_start" type="text" value="0:00" class="insert-video-input-time" />
</label>
            </td>
        </tr>
        <tr>
            <th>&nbsp;</th>
            <td>
                <input type="submit" class="button" value="Insert Video" id="insert-video-button" />
                <span id="insert-video-loading">
                    <span class="insert-video-spinner"></span>
                </span>
            </td>
        </tr>
    </table>
    </form>
    <div class="insert-video-recent-header">
        <input type="hidden" id="show-recent-videos" value="<?php echo get_option('rem_insert_video_show_recent') === FALSE ? '1' : get_option('rem_insert_video_show_recent'); ?>" /> 
        <div class="insert-video-50">
            <h3 class="insert-video-header">Recent Videos</h3>
        </div>
        <div class="insert-video-50">
            <p class="insert-video-text-right"><a href="#" id="insert-video-hide-list">&nbsp;</a></p>
        </div>
        <div style="clear: both;"></div>
    </div>
    <div id="insert-video-recent-list">
        &nbsp;
    </div>
</div>
<script type="text/javascript">;
function insert_video_load_recent_list()
{
    jQuery(function($)
    {
        if ($('#show-recent-videos').val() != '1')
        {
            $('#insert-video-hide-list').html('<?php echo esc_js($this->SHOW_THIS_LIST); ?>');            
            return;
        }
        else
        {
            $('#insert-video-hide-list').html('<?php echo esc_js($this->HIDE_THIS_LIST); ?>');
        }
        $.get( '<?php echo admin_url('admin-ajax.php'); ?>', { action: 'rem_insert_video', subaction: 'get-recent-videos'}, function(data)
        {
            var recent;
            var html = '';
            if (data.recent.length)
            {
                for (var i=0; i<data.recent.length; i++)
                {
                    recent = data.recent[i];
                    html += '<div class="insert-video-25"><div class="insert-video-thumbnail">';
                    var thumb = 'http://img.youtube.com/vi/'+recent.v+'/1.jpg';
                    var url = 'https://www.youtube.com/watch?v='+recent.v;
                    if (recent.list)
                    {
                        url += '&list='+recent.list;
                    }
                    html += '<a href="'+url+'" target="_blank" title="'+url+'"><img src="'+thumb+'" /></a>';
                    var icon_class = (recent.list) ? 'insert-video-multiple-icon' : 'insert-video-single-icon';
                    html += '</div>';
                    html += '<div class="insert-video-recent-links"><div class="insert-video-50"><i class="'+icon_class+'"></i> <a href="#" video-id="'+recent.v+'" class="insert-video-recent-insert">Insert</a></div>';
                    html += '<div class="insert-video-50 insert-video-text-right"><a href="#" video-id="'+recent.v+'" class="insert-video-recent-remove">Remove</a></div></div></div>';
                }
            }
            else
            {
                html = '<p>Recent videos list is empty</p>';
            }
            html += '<div style="clear:both"></div>';
            $('#insert-video-recent-list').html(html);
            $('.insert-video-recent-insert').click(function(e)
            {
                var id = $(this).attr('video-id');
                $.get( '<?php echo admin_url('admin-ajax.php'); ?>', { action: 'rem_insert_video', subaction: 'insert-recent-videos', v: id}, function(data)
                {
                    var list='';
                    if (data.list)
                    {
                        list = '&list='+data.list;
                    }
                    $('#vid_url').val('https://www.youtube.com/watch?v='+data.v+list);
                    $('#vid_start').val(data.start_time);
                    if (data.autoplay == 1) { $('#vid_autoplay').attr('checked', 'checked'); } else { $('#vid_autoplay').removeAttr('checked'); }
                    if (data.autohide == 1) { $('#vid_autohide').attr('checked', 'checked'); } else { $('#vid_autohide').removeAttr('checked'); }
                    if (data.controls == 1) { $('#vid_controls').attr('checked', 'checked'); } else { $('#vid_controls').removeAttr('checked'); }
                    if (data.loop == 1)     { $('#vid_loop').attr('checked', 'checked'); } else { $('#vid_loop').removeAttr('checked'); }    
                    $('div#TB_ajaxContent').animate({ scrollTop: 0 }, 'fast');
                    $('#vid_url').focus();
                }, 'json' );
                e.preventDefault();
            });
            $('.insert-video-recent-remove').click(function(e)
            {
                var id = $(this).attr('video-id');
                $.get( '<?php echo admin_url('admin-ajax.php'); ?>', { action: 'rem_insert_video', subaction: 'remove-recent-videos', v: id}, function(data)
                {
                    insert_video_load_recent_list();
                }, 'json' );
                e.preventDefault();
            });
            $('#insert-video-recent-list').slideDown();
        }, 'json' );
    });
}
jQuery(document).ready(function($)
{
    $('.insert-media').after('<a href="#TB_inline?width=600&height=550&inlineId=add-video-modal" class="thickbox button insert-video" title="Insert Video"><span class="insert-video-icon"></span> Insert Video</a>');
    $('.insert-video').click(function(e)
    {
        $('#insert-video-status').html('<?php echo esc_js($this->VIDEO_EXAMPLE); ?>');
        $('#insert-video-status').removeClass('insert-video-error');
        $('#insert-video-loading').css('display', 'none');
        $('#vid_start').val('0:00');
        $('#vid_url').val('');
    });
    //
    $('#insert-video-hide-list').click(function(e)
    {
        $.get( '<?php echo admin_url('admin-ajax.php'); ?>', { action: 'rem_insert_video', subaction: 'toggle-recent-list'}, function(data)
        {
            $('#show-recent-videos').val(data.show);
            if (data.show == 1)
            {
                $('#insert-video-hide-list').html('<?php echo esc_js($this->HIDE_THIS_LIST); ?>');
                insert_video_load_recent_list();
            }
            else
            {
                $('#insert-video-hide-list').html('<?php echo esc_js($this->SHOW_THIS_LIST); ?>');
                $('#insert-video-recent-list').slideUp();            
            }
        }, 'json' );
        e.preventDefault();
    });
    //
    $('#insert-video-button').click(function(e)
    {
        $('#insert-video-loading').css('display', 'inline-block');
        $.get( '<?php echo admin_url('admin-ajax.php'); ?>', $('#add-video-form').serialize(), function(data)
        {
            $('#insert-video-loading').css('display', 'none');
            if (data.errmsg)
            {
                $('#insert-video-status').html(data.errmsg);
                $('#insert-video-status').addClass('insert-video-error');
            }
            else
            {
                $('#insert-video-status').removeClass('insert-video-error');
                var text = '[<?php echo $this->INSERT_SHORTCODE; ?> id="'+data.v+'"';
                if (data.list != null)  { text += ' list="'+data.list+'"'}
                if (data.autoplay == 1) { text += ' autoplay='+data.autoplay; }
                if (data.autohide == 1) { text += ' autohide='+data.autohide; }
                if (data.controls == 0) { text += ' controls='+data.controls; }
                if (data.loop == 1)     { text += ' loop='+data.loop; }
                if (data.start > 0)     { text += ' start='+data.start; }
                text += ']';
                window.send_to_editor(text);
                insert_video_load_recent_list();
                tb_remove();
            }
        }, 'json' );
        e.preventDefault();
    });
    insert_video_load_recent_list();
});
</script>
<?php
    }
}

$_rem_video = new Responsive_Insert_Video;
?>