<?php
/**
 * Quiz Commenters plugin for WordPress.
 *
 * WordPress plugin header information:
 *
 * * Plugin Name: Quiz Commenters
 * * Description: Require the user to answer a quiz based on the post content before being able to comment.
 * * Version: 0.1
 * * Author: Meitar Moscovitz, Henrik Lied, and Eirik Backer
 * * Author URI: https://maymay.net/
 * * License: GPL-3.0
 * * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * * Text Domain: quiz-commenters
 * * Domain Path: /languages
 *
 * @link https://developer.wordpress.org/plugins/the-basics/header-requirements/
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @package WordPress\Plugin\Quiz_Commenters
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Disallow direct HTTP access.

class Quiz_Commenters {

    /**
     * The name of the quiz's nonce.
     */
    const QUIZ_NONCE = 'quiz-commenters-nonce';

    /**
     * Entry point for the WordPress framework into plugin code.
     *
     * This is the method called when WordPress loads the plugin file.
     * It is responsible for "registering" the plugin's main functions
     * with the {@see https://codex.wordpress.org/Plugin_API WordPress Plugin API}.
     *
     * @uses add_action()
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     *
     * @return void
     */
    public static function register () {
        add_action( 'plugins_loaded', array( __CLASS__, 'registerL10n' ) );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
        add_action( 'comment_form_top', array( __CLASS__, 'form' ) );
        add_action( 'pre_comment_on_post', array( __CLASS__, 'pre_comment_on_post' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
        add_action( 'save_post', array( __CLASS__, 'save' ), 10, 3 );

        register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
    }

    /**
     * Loads localization files from plugin's languages directory.
     *
     * @uses load_plugin_textdomain()
     *
     * @return void
     */
    public static function registerL10n () {
        load_plugin_textdomain( 'quiz-commenters', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Loads the accompanying CSS and JS files for the front-end.
     *
     * @uses wp_enqueue_script
     */
    public static function enqueue_scripts () {
        wp_enqueue_script( 'quizcommenters', plugins_url( 'quiz-commenters.js', __FILE__ ) );
        wp_enqueue_style( 'quizcommenters', plugins_url( 'quiz-commenters.css', __FILE__ ) );
    }

    /**
     * Prints the comment quiz atop WordPress's comment form.
     *
     * This outputs the JavaScript-hooked element and initial greeting. A
     * different function, `form_no_js`, prints the HTML-only
     * version of the same quiz and is used when JavaScript is disabled.
     *
     * @uses self::form_no_js()
     */
    public static function form() {
        if ( self::post_has_quiz( get_post() ) ) {
            $quiz = get_post_meta( get_the_ID(), 'quizcommenters' );
            $answer_hash = hash( 'sha256', serialize( $quiz ) );
            if ( ! isset( $_COOKIE['quizcommenters_comment_quiz_' . COOKIEHASH] ) || $answer_hash !== $_COOKIE['quizcommenters_comment_quiz_' . COOKIEHASH] ) {
    ?>
      <div class="<?php esc_attr_e( 'quizcommenters' ); ?>"
        data-<?php esc_attr_e( 'quizcommenters' ); ?>="<?php echo esc_attr( rawurlencode( json_encode( get_post_meta( get_the_ID(), 'quizcommenters' ) ) ) ); ?>"
        data-<?php esc_attr_e( 'quizcommenters' ); ?>-error="<?php esc_attr_e( 'You have not answered the quiz correctly. Try again.', 'quiz-commenters' ); ?>"
        data-<?php esc_attr_e( 'quizcommenters' ); ?>-correct="<?php esc_attr_e( 'You answered the quiz correctly! You may now post your comment.', 'quiz-commenters' ); ?>"
      >
        <h2><?php esc_html_e( 'Would you like to comment? Please answer some quiz questions from the story.', 'quiz-commenters' );?></h2>
        <p><?php esc_html_e( "We care about our comments.
          That's why we want to make sure that everyone who comments have actually read the story.
          Answer a short quiz about the story to post your comment.
        ", 'quiz-commenters' ); ?></p>
      </div>
    <?php
                self::form_no_js();
            }
        }
    }

    /**
     * Prints the HTML-only quiz at the top of the WordPress comment form.
     */
    private static function form_no_js() {
        $quiz = get_post_meta( get_the_ID(), 'quizcommenters' );
    ?>
        <noscript>
            <?php if ( isset( $_GET['quizcommenters_quiz_error'] ) ) { ?>
                <p class="error"><?php esc_html_e( 'You have not answered the quiz correctly. Try again.', 'quiz-commenters' ); ?></p>
            <?php
            }
            // Retain the user's entered comment even if they got the quiz wrong.
            if ( isset( $_GET['quizcommenters_comment_content' ] ) ) {
                add_filter( 'comment_form_field_comment', function ( $text ) {
                        $pos = strpos( $text, '</textarea>' );
                        return substr_replace(
                            $text,
                            esc_html( rawurldecode( stripslashes_deep( $_GET['quizcommenters_comment_content' ] ) ) ) . '</textarea>',
                            $pos
                        );
                    }
                );
            }

            foreach ( $quiz as $i => $question ) { ?>
                <div class="<?php esc_attr_e( 'quizcommenters' . '-quiz-question-' . $i ); ?>">
                    <h2><?php esc_html_e( $question['text'] ); ?></h2>
                    <ul>
                    <?php
                    // Randomize the order in which answers are shown.
                    $answers = array();
                    foreach ( $question['answer'] as $k => $v ) {
                        $answers[] = array( 'value' => $k, 'text' => $v );
                    }
                    shuffle($answers);
                    foreach ( $answers as $j => $answer ) {
                    ?>
                        <li class="<?php esc_attr_e( 'quizcommenters' ); ?>-quiz-answer-<?php esc_attr_e( $j ); ?>">
                            <label>
                                <input type="radio"
                                    name="<?php esc_attr_e( 'quizcommenters' . $i ); ?>"
                                    value="<?php esc_attr_e( $answer['value'] ); ?>"
                                />
                                <?php esc_html_e( $answer['text'] ); ?>
                            </label>
                    <?php } ?>
                </ul>
            </div>
    <?php
            }
    ?>
        </noscript>
    <?php
    }

    /**
     * Tests a user's answers to the comment quiz before allowing their
     * comment to be added.
     *
     * @param int $post_id
     *
     * @link https://developer.wordpress.org/reference/hooks/pre_comment_on_post/
     */
    public static function pre_comment_on_post( $post_id ) {
        if ( ! self::post_has_quiz( get_post( $post_id ) ) ) {
            return; // Don't do anything on a post without a quiz.
        }

        // Collect correct answers.
        $quiz = get_post_meta( $post_id, 'quizcommenters' );
        $correct_answers = array();
        foreach ( $quiz as $i => $questions ) {
            $correct_answers["quizcommenters$i"] = $questions['correct'];
        }
        $answer_hash = hash( 'sha256', serialize( $quiz ) );

        if ( isset( $_COOKIE['quizcommenters_comment_quiz_' . COOKIEHASH] ) && $answer_hash === $_COOKIE['quizcommenters_comment_quiz_' . COOKIEHASH] ) {
            return; // Don't verify quiz answers if we've already answered them.
        }

        $answers = array_intersect_key( $_POST, $correct_answers );
        $permalink = get_permalink( $post_id );
        if ( ( count( $answers ) !== count( $correct_answers ) ) || array_diff( $answers, $correct_answers ) ) {
            // The user did not answer all quiz question(s) correctly.
            $redirect = $permalink;
            $redirect .= '?' . rawurlencode( 'quizcommenters' . '_quiz_error' ) . '=1';
            $redirect .= '&' . rawurlencode( 'quizcommenters' . '_comment_content' ) . '=' . rawurlencode( $_POST['comment'] );
            wp_safe_redirect( $redirect . '#respond' );
            exit();
        } else {
            // The user answered every question correctly. Proceed. :)
            $secure = ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) );
            $path = parse_url( $permalink, PHP_URL_PATH );
            $comment_cookie_lifetime = apply_filters( 'comment_cookie_lifetime', 3 * HOUR_IN_SECONDS );
            setcookie( 'quizcommenters_comment_quiz_' . COOKIEHASH, $answer_hash, time() + $comment_cookie_lifetime, $path, COOKIE_DOMAIN, $secure, true );
            return;
        }
    }

    /**
     * Registers the quiz's meta box.
     *
     * @uses self::edit
     *
     * @link https://developer.wordpress.org/reference/hooks/add_meta_boxes/
     */
    public static function add() {
      add_meta_box( 'quizcommenters', __( 'Comment Quiz', 'quiz-commenters' ), array( __CLASS__, 'edit' ), 'post', 'side', 'high' );
    }

    /**
     * Prints the quiz's Meta Box when editing a post.
     *
     * @param WP_Post $post
     *
     * @uses print_quiz_question_edit_html
     */
    public static function edit( $post ) {
        self::print_quiz_question_edit_html( $post );
        $addmore = esc_html( __( 'Add question +', 'quiz-commenters' ) );
        echo '<button class="button hide-if-no-js" type="button" data-' . esc_attr( 'quizcommenters' ) . '>' . esc_html( $addmore ) . '</button>';
    ?><script>
        // Add another question to the quiz editing form.
        document.addEventListener('click', function(event){
          if(event.target.hasAttribute('data-<?php print esc_js( esc_attr( 'quizcommenters' ) ); ?>')){
            var button = event.target;
            var index = [].indexOf.call(button.parentNode.children, button);
            var clone = button.previousElementSibling.cloneNode(true);
            var title = clone.querySelector('strong');

            title.textContent = title.textContent.replace(/\d+/, index + 1);
            [].forEach.call(clone.querySelectorAll('input'), function(input){
              input.name = input.name.replace(/\d+/, index);  //Update index
              if(input.type === 'text')input.value = '';      //Reset value
            });
            button.parentNode.insertBefore(clone, button);    //Insert in DOM
          }
        });
      </script>
      <?php wp_nonce_field( 'quizcommenters', self::QUIZ_NONCE );
    }

    /**
     * Prints the HTML for a quiz question in the edit form.
     *
     * @param WP_Post $post
     */
    private static function print_quiz_question_edit_html ( $post ) {
        $quiz = get_post_meta( $post->ID, 'quizcommenters' );
        $questions = array_pad( $quiz, count($quiz) + 1, array() );

        $answer = esc_attr( __( 'Answer', 'quiz-commenters' ) );
        $ask = esc_attr( __( 'Question', 'quiz-commenters' ) );
        $correct = esc_html( __( 'Correct', 'quiz-commenters' ) );

        foreach( $questions as $index => $question ) {
            $title = __( 'Question', 'quiz-commenters' ) . ' ' . ( $index + 1 );
            $text = esc_attr( empty( $question['text'] ) ? '' : $question['text'] );
            $name = 'quizcommenters' . '[' . $index . ']';

            echo '<div style="margin-bottom:1em;padding-bottom:1em;border-bottom:1px solid #eee">';
            echo '<p><label><strong style="display:block;">' . esc_html( $title ) . ':</strong><input type="text" name="' . esc_attr( $name ) . '[text]" placeholder="' . esc_attr( $ask ) . '" value="' . esc_attr( $text ) . '"></label></p>';
            echo '<ul>';
            for( $i = 0; $i < 3; $i++ ) {
                $check = checked( $i, isset( $question['correct'] ) ? intval( $question['correct'] ) : 0, false );
                $value = isset( $question['answer'][ $i ] ) ? esc_attr( $question['answer'][ $i ] ) : '';

                echo '<li>';
                echo '<input type="text" name="' . esc_attr( $name ) . '[answer][' . esc_attr( $i ) . ']" placeholder="' . esc_attr( $answer ) . '" value="' . esc_attr( $value ) . '">';
                echo '<label><input type="radio" name="' . esc_attr( $name ) . '[correct]" value="' . esc_attr( $i ) . '"' .$check . '> ' . esc_html( $correct ) . '</label>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Saves a post's commenting quiz to the database on post save.
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     *
     * @link https://developer.wordpress.org/reference/hooks/save_post/
     */
    public static function save( $post_id, $post, $update ) {
        if( isset( $_POST['quizcommenters'], $_POST[self::QUIZ_NONCE] ) && wp_verify_nonce( $_POST[self::QUIZ_NONCE], 'quizcommenters' ) ) {
            // Clean up previous quiz meta
            delete_post_meta( $post_id, 'quizcommenters' );
            foreach( $_POST['quizcommenters'] as $k => $v ) {
                // Only save filled in questions
                if( $v['text'] && array_filter( $v['answer'], 'strlen' ) ) {
                    add_post_meta( $post_id, 'quizcommenters', $v );
                }
            }
        }
    }

    /**
     * Determines whether or not a given post has an associated quiz.
     *
     * @param WP_Post $post
     *
     * @return bool
     */
    public static function post_has_quiz( $post ) {
        return ( empty( get_post_meta( $post->ID, 'quizcommenters', true ) ) ) ? false : true;
    }

}

Quiz_Commenters::register();
