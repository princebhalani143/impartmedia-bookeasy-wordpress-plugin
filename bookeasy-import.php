<?php


class BookeasyOperators_Import{

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    private $catOptions;

    public $optionGroup = 'BookeasyOperators_options';
    public $optionGroupCategories = 'BookeasyOperators_categoriessync';
    public $postmetaPrefix = 'bookeasy';

    private $postFields = array(
        'post_title' => 'TradingName',
        'post_content' => 'Description',
    );

    /**
     * Start up
     */
    public function __construct(){

        //add_action( 'bookeasyoperators_daily_event_hook', array( $this, 'sync' ) );

        //returning for chaining
        return $this;
    }


    public function sync(){

        global $wpdb;

        $this->options = get_option($this->optionGroup);

        $url = $this->options['url'];
        $postType = $this->options['posttype'];

        if(empty($url) || empty($postType)){
            return 'Please set the url and post type';
        }

        // create the url and fetch the stuff
        $json = file_get_contents($url);
        $arr = json_decode($json, true);

        //var_dump($arr);
    
        if(!isset($arr['Operators']) || !is_array($arr['Operators'])){
            return 'Url/Json Fail';
        }

        $create_count = 0;
        $update_count = 0;
        foreach($arr['Operators'] as $op){

            // check if it exists based on the operator id
            $postMeta_query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '{$this->postmetaPrefix}_OperatorID' AND meta_value = %d";
            $postMeta_postId = $wpdb->get_var($wpdb->prepare($postMeta_query, $op['OperatorID']));

            $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID = %d", $postMeta_postId));

            if(empty($op[$this->postFields['post_title']])){
                continue;
            }

            // Create the post array
            $post = array(
              'post_content'   => $op[$this->postFields['post_content']],
              'post_title'     => $op[$this->postFields['post_title']], 
              'post_status'    => 'publish',
              'post_type'      => $postType,
            );  

            // Does this operator id exist already?
            if(!empty($post_id)){
                $post = array_merge($post, array('ID' => $post_id));
            } 

            //ram this thing in the database.
            $inserted_id = wp_insert_post( $post );

            // something happed??
            if( is_wp_error( $inserted_id ) ) {
                return $return->get_error_message();
            }

            // add the rest of the field in to post data
            foreach($op as $opKey => $opItem){
                if(in_array($opKey, $this->postFields)){
                    continue;
                }
                update_post_meta($inserted_id, $this->postmetaPrefix . '_' . $opKey, $opItem);
            }

            if(!empty($post_id)){
                $update_count++;
            } else {
                $create_count++;
            }
        }

        return 'Created:' . $create_count . ' Updated:'.$update_count. ' '; 

    }



    public function cats(){

        global $wpdb;

        $this->options = get_option($this->optionGroup);
        $this->catOptions = get_option($this->optionGroupCategories);

        $url = $this->options['url'];

        if(empty($url)){
            return 'Please set the url and post type';
        }

        // create the url and fetch the stuff
        $json = file_get_contents($url);
        $arr = json_decode($json, true);

        if(!isset($arr['Operators']) || !is_array($arr['Operators'])){
            return 'Url/Json Fail';
        }

        $types = array();
        foreach($arr['Operators'] as $op){

            // add the rest of the field in to post data
            foreach($op as $opKey => $opItem){
                if(strstr($opKey, 'Type')){
                    $types[] = $opKey . '|' .$opItem;
                }
            }

        }

        $this->catOptions['bookeasy_cats'] = array_unique($types);
        update_option($this->optionGroupCategories, $this->catOptions);
        return count($this->catOptions['bookeasy_cats']) . ' Unique Categories <script> location.reload(); </script>'; 

    }

}

new BookeasyOperators_Import();

