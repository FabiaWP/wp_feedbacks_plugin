<?php
/*
  Plugin Name: FeedBacks Fabia 1
  Plugin URI:

 */
define('FO_FEEDBACKS_PATH', dirname(__FILE__));
define('FO_FEEDBACKS_FOLDER', basename(FO_FEEDBACKS_PATH));
define('FO_FEEDBACKS_URL', plugins_url() . '/' . FO_FEEDBACKS_FOLDER);


$post = isset($_GET['post']) ? $_GET['post'] : false;  // trovo l'id

/**
* @return bool
*/

function retrieve_the_term()
{
$post = isset($_GET['post']) ? $_GET['post'] : false;  // trovo l'id
$custom = get_post_custom($post); //trovo il team del feedback
$belong_team= $custom['wpcf-team-of-the-pr'];//todo: se non è impostato il team esce messaggio di errore-->controlla.
    return $belong_team;
}





if($post) {



add_filter('wpt_field_options', 'fo_members_options', 10, 2);

}

function fo_members_options($options, $title)
{
$post=isset($_GET['post']) ? $_GET['post'] : false;  // trovo l'id

$options = array();


if ('Team of the PR' === $title) {
    $terms = get_terms('team', 'orderby=count&hide_empty=0' );

    /* @var $term WP_Term */
    foreach ($terms as $term) {
        $options[] = array(
            '#value' => $term->term_id,
            '#title' => $term->name,
        );
}



}


    if ('Team of the member' === $title) {
        $terms = get_terms('team', 'orderby=count&hide_empty=0' );

        /* @var $term WP_Term */
        foreach ($terms as $term) {
            $options[] = array(
                '#value' => $term->term_id,
                '#title' => $term->name,
            );
        }



    }

if ('Receiver' === $title) {

$belong_team = retrieve_the_term();
$args = array(
'post_type' => 'member',
'post_status' => 'publish',
'meta_key' => 'wpcf-team-of-the-member',
'meta_value' => $belong_team, // seleziono soltanto i membri appartenenti a un certo team
);


$posts_array = get_posts($args);

/* @var $post WP_Post */
foreach ($posts_array as $post2) {
$options[] = array(
'#value' => $post2->ID,
'#title' => $post2->post_title,
);

}
}


if ('Giver'=== $title) {
$options = array();

$args = array(
'post_type' => 'member',
'post_status' => 'publish',

);



$posts_array = get_posts($args);

/* @var $post WP_Post */
foreach ($posts_array as $post2) {
$options[] = array(
'#value' => $post2->ID,
'#title' => $post2->post_title,
);

}
}



return $options;

}

//qui inizia il plugin table



add_shortcode('get-the-table', 'make_the_table_1'); //questo è da mettere nelle pagine delle singole pr
add_shortcode('get-the-table-2','team_report'); //questo è da mettere nelle pagine dei team
add_shortcode('summary','make_summary_table'); //questo è da mettere nel riassunto

/**
 * @param $peer_review_id
 * @return array
 */

function get_the_members($team){

    $receivers_table = array();



    $args = array(
        'post_type' => 'member',
        'post_status' => 'publish',
        'meta_key' => 'wpcf-team-of-the-member',
        'meta_value' => $team, // seleziono soltanto i membri appartenenti a un certo team
    );

    $posts_array = get_posts($args);
    foreach ($posts_array as $post2) {
        $receivers_table[] = $post2->post_title;

    }

    return $receivers_table;


}



function  get_the_list_of_the_teams()
{
    $url = site_url();
    $info=array();


    $terms = get_terms('team', 'orderby=count&hide_empty=0' );

    /* @var $term WP_Term */
    foreach ($terms as $term) {


        $string= $term->term_id;
        $url_of_the_page= $url . '/reporter-' . $string;
        $info[$term->term_id] = array(
            'title' => $term->name,
            'url'=>$url_of_the_page,
        );


    }

    return $info;
} //lista di tutti i tem



function sort_givers_by_team_and_name($givers)
{

    $givers_and_team = array();

    foreach ($givers as $giver) {
        $team_id = get_team_id_from_giver($giver);

        $giver_and_team = array(
            "team_id" => $team_id,
            "name" => $giver
        );

        $term = get_term($team_id, 'team');


        $giver_and_team['team'] = $term->name;

        $givers_and_team[] = $giver_and_team;
    }

    $sort = array();
    foreach ($givers_and_team as $k => $v) {
        $sort['teams'][$k] = $v['team'];
        $sort['names'][$k] = $v['name'];
    }

    array_multisort($sort['teams'], SORT_ASC, $sort['names'], SORT_ASC, $givers_and_team);

    return $givers_and_team;

}


function get_team_id_from_giver($giver)
{
    $args = array(
        'post_type' => 'member',
        'post_status' => 'publish',
        'title' => $giver,

    );

    $posts_array = new WP_Query($args);
    $post_id = $posts_array->posts[0]->ID;
    $field_value = get_post_meta($post_id, 'wpcf-team-of-the-member', true);

    $field_split= explode('-', $field_value);
    $term_id = $field_split[count($field_split) - 1];

    return $term_id;

}


function get_the_feedbacks($peer_review_id)

{
    $table_data = array();
    $receivers = array();
    $givers = array();
    $args = array(
        'post_type' => array('feedback'),
        'post_status' => array('publish'),
        'meta_query' => array(
            array(
                'key' => '_wpcf_belongs_peer-review_id',
                'value' => $peer_review_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    );
    $query = new WP_Query($args);
    $posts_array = $query->get_posts();

    foreach ($posts_array as $post) {


        $receiver = get_the_reveicer_name($post);
        $giver = get_the_giver_name($post);
        $opinion = get_post_meta($post->ID, 'wpcf-opinion', true);
        $table_data[$receiver][$giver] = $opinion;
        $receivers[] = $receiver;
        $givers[]=$giver;


    }

    $receivers = array_unique($receivers);

    $givers_better=sort_givers_by_team_and_name($givers);

    return array($table_data, $receivers, $givers_better);


} //trova tutti i fb di una pr

function get_the_reviews($team)

{
    $args_by_team = array(
        'post_type' => array('peer-review'),
        'post_status' => array('publish'),
        'meta_query' => array(
            array(
                'key' => 'wpcf-team-of-the-pr',
                'value' => $team,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    );
    $query = new WP_Query($args_by_team);
    $reviews_array = $query->get_posts();  //trovo tutte le pr appartenenti a un certo team

    $table_data_2 = array();
    $links=array();

    foreach ($reviews_array as $review) {
        $review_id = $review->ID;
        $link=get_permalink($review->ID);

        $peer_review_date = get_post_meta($review_id, 'wpcf-date', true);
        $array_of_dates[] = $peer_review_date;
        $table_data_2_content = feedback_counter_of_the_peer_review($review_id);
        $table_data_2[$peer_review_date]= ($table_data_2_content);
        $links[$peer_review_date]=$link;


    }


    make_the_table_2($table_data_2,$links,$team);

} //trova tutte le pr del team

/**
 * @param WP_Post $post
 * @return string
 */
function get_the_giver_name($post)
{
    return get_member_name($post, 'giver');
}

/**
 * @param WP_Post $post
 * @return string
 */
function get_the_reveicer_name($post)
{
    return get_member_name($post, 'receiver');
}

function get_member_name($post, $key)
{
    $member_id = get_post_meta($post->ID, 'wpcf-' . $key, true);
    return get_the_title($member_id);
}




function team_report($atts){

    extract( shortcode_atts( array(
        'team' => ''
    ), $atts ) );

    $team=$atts['team'];
    get_the_reviews($team);

}  //legge lo shortcode e trova il team

function feedback_counter_of_the_peer_review($peer_review_id)
{
    $counter=array();
    list($table_data, $receivers, $givers) = get_the_feedbacks($peer_review_id);

    foreach($receivers as $receiver_a)
    {
        $counter[$receiver_a]=0;
    }

    foreach ($givers as $giver_a){

        $giver_name = $giver_a['name'];
        foreach ($receivers as $key => $receiver_a){
            if (isset ($table_data[$receiver_a][$giver_name])) {$counter[$receiver_a]=$counter[$receiver_a]+1;}

        }
    }


    return $counter;
}  //conta i fb di una peer review x ciscun membro del team









function make_the_table_1()  //make table 1
{   $peer_review_id = get_the_ID();

    if (!$peer_review_id) {
        return '';
    }
    else {
        //$peer_review_date = get_post_meta($peer_review_id, 'wpcf-date', true);
        //sort($peer_review_date);
    }


    list($table_data, $receivers, $givers) = get_the_feedbacks($peer_review_id);
    ?>
    <!-- format them inside the table -->
    <table border="1" cellpadding="10">
        <thead>
        <tr>
            <th></th>
            <?php foreach ($receivers as $receiver_a): ?>
                <th><?php echo $receiver_a; ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($givers as $giver_a): ?>

            <tr>
                <td><?php $giver_name = $giver_a['name'];
                    echo $giver_name; ?></td> <!-- loop they time -->
                <?php foreach ($receivers as $key => $receiver_a):
                    ?>
                    <td>
                        <?php
                        if (!isset ($table_data[$receiver_a][$giver_name])) {
                            echo '**********';
                        } else {
                            echo $table_data[$receiver_a][$giver_name];


                        }
                        ?>
                    </td>
                <?php endforeach; ?>

            </tr>

        <?php endforeach; ?>
        </tbody>
    </table>


    <?php

    feedback_counter_of_the_peer_review($peer_review_id);

}




function make_the_table_2($table_data_2,$links,$team)  //make table 2

{   $receivers=get_the_members($team);
    $array_of_dates=array_keys($table_data_2);
    ?>

    <table border="1" cellpadding="10">
    <thead>
    <tr>
        <th></th>
        <?php foreach ($receivers as $receiver_a): ?>
            <th><?php echo $receiver_a; ?></th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($array_of_dates as $date): ?>

        <tr>
            <td> <a href="<?php echo $links[$date]; ?>"><?php
                echo date('mv Y-m-d',$date); ?></td> <!-- loop they time -->
            <?php foreach ($receivers as $receiver_a):
                ?>
                <td>
                    <?php
                    if (!isset ($table_data_2[$date][$receiver_a])) {
                        echo '**********';
                    } else {
                        echo $table_data_2[$date][$receiver_a];
                    }
                    ?>
                </td>
            <?php endforeach; ?>

        </tr>

    <?php endforeach; ?>
    </tbody>
</table>

<?php
}




function make_summary_table(){

    $table_data_3=get_the_list_of_the_teams();
    ?>
    <table border="1" cellpadding="10">
        <tbody>
        <?php foreach ($table_data_3 as $team):?>
            <tr><td><a href= <?php echo $team['url'] ?>> <?php echo $team['title']; ?></a></td></tr>

        <?php endforeach; ?>
        </tbody>
    </table>


    <?php

}  //make table 3

