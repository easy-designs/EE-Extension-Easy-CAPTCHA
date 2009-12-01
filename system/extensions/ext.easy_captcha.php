<?php
/*
=====================================================
 Easy CAPTCHA Challenges - by Easy! Designs, LLC
-----------------------------------------------------
 http://www.easy-designs.net/
=====================================================
 This extension was created by Matt Harris
 - matt@easy-designs.net
=====================================================
 File: ext.easy_captcha.php
-----------------------------------------------------
 Purpose: Replaces the default CAPTCHA challenge with
          simple questions provided by the 
          administrator
=====================================================
*/

if ( ! defined('EXT')) {
  exit('No direct script access allowed');
}

class Easy_captcha {
  var $settings        = array();

  var $name            = 'Easy CAPTCHA Challenges';
  var $version         = '1.0';
  var $description     = 'Replaces the image CAPTCHA challenge with simple questions provided by the administrator.';
  var $settings_exist  = 'y';
  var $docs_url        = '';



  /**
   * Constructor 
   */
  function Easy_captcha($settings='') 
  {
    $this->settings = $settings;
  }
  
  // --------------------------------------------------------------------
  
  /**
   * Activate Extension by registering it in the database
   */
  function activate_extension() 
  {
    global $DB, $PREFS;

    $DB->query(
      $DB->insert_string(
        $PREFS->ini('db_prefix') . '_extensions',
        array(
          'extension_id' => '',
          'class'        => __CLASS__,
          'method'       => "create_captcha",
          'hook'         => "create_captcha_start",
          'settings'     => serialize($this->settings),
          'priority'     => 1,
          'version'      => $this->version,
          'enabled'      => "y"
        )
      )
    );
  }

  // --------------------------------------------------------------------
  
  /**
   * Updates the extension to the most recent version, if it was already 
   * installed.
   * @param string $current the currently installed version of the extension
   */
  function update_extension($current='') 
  {
    global $DB, $PREFS;

    if ($current == '' OR $current == $this->version) {
      return FALSE;
    }

    if ($current < '1.0') {
      // Actions required to update to version 1.0
    }

    $DB->query(
      $DB->update_string(
  		  $PREFS->ini('db_prefix') . '_extensions', 
  		  array( 'version' => $this->version ), 
  		  array( 'class' => __CLASS__ ) 
  		)
  	);
  }

  // --------------------------------------------------------------------

  /** 
   * Deactivates the extension and removes any settings we had stored
   */
  function disable_extension() 
  {
    global $DB, $PREFS;
    $DB->query("DELETE FROM ".$PREFS->ini('db_prefix')."_extensions 
                WHERE class = '" . __CLASS__ . "'");
  }
  
  // --------------------------------------------------------------------
  
  /**
   * The settings for the extension. Not used as we are rendering our own
   * form using settings_form
   * @return array the settings for the extension
   */
  function settings() 
  {
    $settings = array();
    
    // $settings['key'] = '';

    return $settings;
  }
  
  // --------------------------------------------------------------------
  
  /**
   * Tells ExpressionEngine how to render the control panel for this extension
   * @param $current the current settings
   */
  function settings_form($current) 
  {
    global $DSP, $LANG, $IN;

    $DSP->crumbline = TRUE;

    $DSP->title  = $LANG->line('extension_settings');
    $DSP->crumb  = $DSP->anchor(
                      BASE.
                      AMP.'C=admin'.
                      AMP.'area=utilities', 
                      $LANG->line('utilities')
                    ).
                    $DSP->crumb_item($DSP->anchor(
                      BASE.AMP.'C=admin'.
                      AMP.'M=utilities'.
                      AMP.'P=extensions_manager', 
                      $LANG->line('extensions_manager'))
                    );
    $DSP->crumb .= $DSP->crumb_item($this->name);

    $DSP->right_crumb($LANG->line('disable_extension'), 
                      BASE.AMP.'C=admin'.
                      AMP.'M=utilities'.
                      AMP.'P=toggle_extension_confirm'.
                      AMP.'which=disable'.
                      AMP.'name='.$IN->GBL('name')
                    );

    $DSP->body = $DSP->form_open(
      array(
        'action' => 'C=admin' . AMP . 'M=utilities' . AMP . 'P=save_extension_settings',
        'name'   => 'easy_captcha_settings',
        'id'     => 'easy_captcha_settings'
      ),
      array( 'name' => get_class($this) )
    );

    $DSP->body .= $DSP->qdiv('', $LANG->line('instructions'));
    
    $DSP->body .= $DSP->table('tableBorder', '0', '', '100%');
    $DSP->body .= $DSP->tr();
    $DSP->body .= $DSP->td('tableHeadingAlt', '', '2');
    $DSP->body .= $this->name;
    $DSP->body .= $DSP->td_c();
    $DSP->body .= $DSP->tr_c();
    
    // extract the challenges from the current settings
    if ( empty( $current ) )
    {
      $challenges = array(
        array(
          'q' => $LANG->line('example_question'),
          'a' => $LANG->line('example_answer')
        )
      );
    } else {
      $challenges = $current;
    }

    for ( $i=0; $i <= count($challenges); $i++ )
    { 
      $class = ($i % 2) ? 'tableCellOne' : 'tableCellTwo';
      $chal = isset($challenges[$i]) ? $challenges[$i] : '';
      
      $DSP->body .= $DSP->tr();
      $DSP->body .= $DSP->td($class, '45%');
      $DSP->body .= $DSP->qdiv('', $LANG->line('question', 'q'.$i));
      $DSP->body .= $DSP->input_text('q'.$i, ( ! isset($chal['q'])) ? '' : $chal['q']);
      $DSP->body .= $DSP->td_c();

      $DSP->body .= $DSP->td($class);
      $DSP->body .= $DSP->qdiv('', $LANG->line('answer', 'a'.$i));      
      $DSP->body .= $DSP->input_text('a'.$i, ( ! isset($chal['a'])) ? '' : $chal['a']);
      $DSP->body .= $DSP->td_c();
      $DSP->body .= $DSP->tr_c();
    }
    
    $DSP->body .= $DSP->table_c();
    $DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit());
    $DSP->body .= $DSP->form_c();
  }
  
  // --------------------------------------------------------------------
  
  /**
   * Saves the settings returned from the settings form
   * @param $current the current settings
   */
  function save_settings()
  {
    global $DB, $PREFS;
    
		// clear the settings
		$this->settings	= array();
    
    foreach ($_POST as $k => $v)
    {
      if ($k[0] == 'q')
      {
        // get the answer for this question
        $ans = strtolower($_POST['a'.substr($k, 1)]);
        
        // don't store empty challenges
        if (empty($v) || empty($ans)) continue;
        
        // store the challenge
        $challenge = array('q' => $v, 'a' => $ans);

        // store the serialized challenge
        $this->settings[] = $challenge;
      }
    }
    
		// save the settings to the database
		$DB->query(
  		$DB->update_string(
  		  $PREFS->ini('db_prefix') . '_extensions', 
  		  array( 'settings' => addslashes( serialize( $this->settings ) ) ), 
  		  array( 'class' => __CLASS__ ) 
  		)
		);
		
		return TRUE;
	}
  
  // --------------------------------------------------------------------
  
  /**
   * Retrieves a random question from the extension settings and outputs it
   * to the screen.
   * On form submit this checks the submitted answer matches the answer stored
   * with the captcha challenge
   * @param string $old_word the word which was to be the captcha challenge
   * @return string the new Captcha HTML
   */
  function create_captcha($old_word = '') 
  {
    global $EXT, $DB, $IN, $PREFS;
    
    // life time of a captcha answer in the database - same as EE Captcha
    $expiration = 60*60*2;  // 2 hours
     
    // this is the last thing we want to execute for this hook
    $EXT->end_script = TRUE;
     
    // disable DB Caching if it's already on
		$db_reset = FALSE;
		if ($DB->enable_cache == TRUE) {
      $DB->enable_cache = FALSE;
      $db_reset = TRUE;
		}    
		
		// clean up expired captchas
		$old = time() - $expiration;
		$DB->query("DELETE FROM ".$PREFS->ini('db_prefix')."_captcha WHERE date < ".$old);	
		
    $question = '';
    $word = '';
    
    if ($old_word !== '') {
      // we need to get the question for the associated word
      foreach ($this->settings as $challenge) {
        if ($challenge['a'] == $old_word) $question = $challenge['q'];
        break;
      }
  		$word = $old_word;
    }
    
    if ($question === '') {
      // either the old word was empty, or it wasn't found as an answer

      // select a question at random from those provided
      $challenge = $this->settings[array_rand($this->settings)];
    
      // insert the answer into the database so EE handles the check for us
      $DB->query(
        "INSERT INTO `{$PREFS->ini('db_prefix')}_captcha`
           ( `date`, `ip_address`, `word` )
         VALUES
           ( UNIX_TIMESTAMP(), '{$IN->IP}', '{$DB->escape_str($challenge['a'])}' )"
      );
      
      $word = $challenge['a'];
      $question = $challenge['q'];
    }
    
    $this->cached_captcha = $word;
     
    // reenable DB Caching
    if ($db_reset == TRUE) $DB->enable_cache = TRUE;                

    // return the question to the user
    return $question;
  }


   
}

/* End of file ext.easy_captcha.php */
/* Location: ./system/extensions/ext.easy_captcha.php */