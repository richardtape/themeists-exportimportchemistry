<?php
/*
Plugin Name: Themeists Export/Import Chemistry Data
Plugin URI: #
Description: This plugin allows you to export and import your chemistry data that you have saved in posts and pages.
Version: 1.0
Author: Themeists
Author URI: #
License: GPL2
*/

if( !class_exists( 'ThemeistsExportImportChemistryData' ) ):


	/**
	 * Export and Import of Chemistry data. For each post that has chemistry data we have an 
	 * entry in the postmeta table wuth a key of chemistry_molecule_data with the value being
	 * a serialised array of the data. We also have a separate key of chemistry_editor_tab
	 * which, if set to molecule, means that the Chemistry editor is loaded by default (rather
	 * than the html/visual editor).
	 *
	 * @author Richard Tape
	 * @package ThemeistsExportImportChemistryData
	 * @since 1.0
	 */
	
	class ThemeistsExportImportChemistryData
	{


		/**
		 * We might not be using a themeists theme (which means we can't add anything to the options panel). By default,
		 * we'll say we are not. We check if the theme's author is Themeists to set this to true during instantiation.
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 */
		
		var $using_themeists_theme = false;
		

		/**
		 * Initialise ourselves and do a bit of setup
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 * @param None
		 * @return None
		 */

		function ThemeistsExportImportChemistryData()
		{

			$theme_data = wp_get_theme();
			$theme_author = $theme_data->display( 'Author', false );

			if( strtolower( trim( $theme_author ) ) == "themeists" )
				$this->using_themeists_theme = true;


			if( $this->using_themeists_theme )
			{

				//If we're using a themeists theme, we should add our export/import method to our
				//theme option panel, rather than a standalone menu

				//First, let's add the image upload option to the themeists options panel
				add_action( 'of_set_options_after_defaults', 	array( &$this, 'add_import_export_tab_and_options' ), 10, 1 );

				//For the import part of this, we hook into 'of_save_options_before' which is run in options-framework.php
				//just before the normal save routines. As we're not saving anything to the database - per sé - we don't
				//need to add a filter
				add_action( 'of_save_options_before', 			array( &$this, 'import_routine' ), 10, 1 );

				//Finally, let's add some extra tidbits of help in the help tab
				add_action( 'of_set_options_in_help_end', 		array( &$this, 'ThemeistsExportImportChemistryData_faqs' ), 11, 1 );

			}
			else
			{

				//We are not using a themeists theme, so - as we need an administration panel - we
				//need to create one and display our markup in there

			}

		}/* ThemeistsExportImportChemistryData() */




		

		/**
		 * If we are using a themeists theme (so $this->using_themeists_theme is true) then we add options to the 
		 * theme options panel.
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 * @param None
		 * @return None but added theme optionsmenu tab and options
		 */

		function add_import_export_tab_and_options()
		{

			global $options;

			// Import/Export Heading ================================================

			$options[] = array(
				'name' => __( 'Import/Export', THEMENAME ),
				'type' => 'heading'
			);

			$options[] = array(
				'name' => __( 'Export Data', THEMENAME ),
				'desc' => __( 'This is the raw data of all of your posts and pages that contain Chemistry data. If you copy and paste this into the "import" box on the site you wish to move this data to (and then save) then everything will be copied across to that other site.', THEMENAME ),
				'id' => 'chemistry_export_data',
				'std' => self::get_chemistry_data(),
				'type' => 'textarea'
			);

			$options[] = array(
				'name' => __( 'Import Data', THEMENAME ),
				'desc' => __( 'If you paste data from a different site into this box (and then save) we will attempt to create posts and pages with the appropriate chemistry data. <strong>You should only try this method if you have attempted to use the normal in-built WordPress import/export method and the chemistry data has not been transferred.</strong>', THEMENAME ),
				'id' => 'chemistry_import_data',
				'std' => '',
				'type' => 'textarea'
			);

			do_action( 'of_set_options_after_import_export' );

			// End Import/Export Heading ============================================

		}/* add_import_export_tab_and_options() */





		/**
		 * Get the raw data for the export box. We run a db query to grab all posts that have
		 * postmeta attached to them that have the chemistry_molecule_data set to non-empty.
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 * @param None
		 * @return (serialised array i.e. string) of chemistry data
		 */

		function get_chemistry_data()
		{

			global $wpdb;

			//Get the post IDs of all posts that have chemistry data
			$chemdata_posts_query = "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'chemistry_molecule_data'";
			$chemdata_posts = $wpdb->get_results( $chemdata_posts_query, ARRAY_A );

			//Set up an array for our data
			$all_chem_posts = array();

			//Now we have an array with all post IDs, we iterate over each of them to get the raw post data
			foreach( $chemdata_posts as $post )
			{

				if( !empty( $post['meta_value'] ) && ( $post['meta_value'] != "" ) && ( $post['meta_value'] != "a:0:{}" ) )
				{

					/*$post_object = get_post( $post['post_id'] );

					$all_chem_posts[] = array( $post_object, $post['meta_value'] );*/

					//Retrieve the normal WP bits and bobs with get_post() and then append our metadata to that object
					$post_object = get_post( $post['post_id'] );
					$post_object->chemdata = $post['meta_value'];

					$post_array = (array) $post_object;

					$all_chem_posts[] = $post_array;

				}

			}

			$return = serialize( $all_chem_posts );

			if( $return == "a:0:{}" || !$return || $return == "" )
				return __( "We can't find any posts or pages with Chemistry Data", THEMENAME );
			else
				return $return;


		}/* get_chemistry_data() */




		/**
		 * Processes the import data and creates the posts and metadata as necessary
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 * @param (array) $options - The *entire* options array (one of which is our import data)
		 * @return None
		 */

		function import_routine( $options )
		{

			//First we need to check that we're coming from where we should be
			if( $_POST['option_page'] == 'optionsframework' )
			{

				//In the $_POST array, we have an array which is the 'id' ofour options. Let's get that first
				$option_name_array = get_option( 'optionsframework' );
				$option_name = $option_name_array['id'];

				//Get the imported data
				if( array_key_exists( $option_name, $_POST ) && array_key_exists( 'chemistry_import_data', $_POST[$option_name] ) )
				{

					$import_data = $_POST[$option_name]['chemistry_import_data'];

					//The imported data is an array of arrays. We first need to strip the slashes so we have
					// a proper item to unserialize, then we unserialize is and we're left with an array of
					//arrays. For each one of these, there is the normal post data (as you would get with a
					//$post object and, tacked onto each is a chemdata key which contains the serialized chem
					//data )

					$prepare_data = stripslashes( $import_data );
					$unserialized_import_data = unserialize( $prepare_data );

					if( is_array( $unserialized_import_data ) )
					{

						foreach( $unserialized_import_data as $post_id => $post_data )
						{

							$post_exists = self::get_post_by_title( $post_data['post_title'] );

							if( $post_exists && is_array( $post_exists ) )
							{
								
								//A post with this title exists, so let's just add our chemistry data to that

								//First step is to strip any sillyness out of the chemdata
								$chemdata = stripslashes( mysql_real_escape_string( $post_data['chemdata'] ) );

								$meta_key = "chemistry_molecule_data";
								$meta_value = $chemdata;
								global $wpdb;
								$wpdb->query( $wpdb->prepare( 
									"
										INSERT INTO $wpdb->postmeta
										( post_id, meta_key, meta_value )
										VALUES ( %d, %s, %s )
									", 
								    $post_data['ID'], 
									$meta_key, 
									$meta_value 
								) );

								//Ensure the editor is selected
								$wpdb->query( $wpdb->prepare( 
									"
										INSERT INTO $wpdb->postmeta
										( post_id, meta_key, meta_value )
										VALUES ( %d, %s, %s )
									", 
								    $post_data['ID'], 
									'chemistry_editor_tab', 
									'molecule' 
								) );

							}
							else
							{

								//This post doesn't exist at all, so let's create it, then add the chemistry
								//data to that
								$new_post_data = ( array ) $post_data;

								//Knock off the things we don't need
								$new_post_data['ID'] = '';
								$new_post_data['guid'] = '';

								//Make the post based on this data
								$new_post = wp_insert_post( $new_post_data );

								//Add our metadata to that post
								$chemdata = stripslashes( mysql_real_escape_string( $post_data['chemdata'] ) );
								$meta_key = "chemistry_molecule_data";
								$meta_value = $chemdata;

								global $wpdb;
								$wpdb->query( $wpdb->prepare( 
									"
										INSERT INTO $wpdb->postmeta
										( post_id, meta_key, meta_value )
										VALUES ( %d, %s, %s )
									", 
								    $new_post, 
									$meta_key, 
									$meta_value 
								) );

								//Also make sure the chemistry editor is loaded when the post is opened
								$wpdb->query( $wpdb->prepare( 
									"
										INSERT INTO $wpdb->postmeta
										( post_id, meta_key, meta_value )
										VALUES ( %d, %s, %s )
									", 
								    $new_post, 
									'chemistry_editor_tab', 
									'molecule' 
								) );


							}

						}

					}

				}

			}

		}/* import_routine() */




		function get_post_by_title( $title_str )
		{

			global $wpdb;
			return $wpdb->get_row( "SELECT * FROM wp_posts WHERE post_title = '" . $title_str . "'", 'ARRAY_A' );

		}/* get_post_by_title */



		/**
		 * Add an FAQ to the themeists option panel
		 *
		 * @author Richard Tape
		 * @package ThemeistsExportImportChemistryData
		 * @since 1.0
		 * @param None
		 * @return Added FAQ
		 */
		
		function ThemeistsExportImportChemistryData_faqs()
		{

			global $options;

			// Favicons ============================================

			$options[] = array(
				'name' => __( 'Added from ExportImport Plugin?', 'ThemeistsExportImportChemistryData' ),
				'desc' => __( '<p>Answer</p>', 'ThemeistsExportImportChemistryData' ),
				'id' => 'exportimport_q1',
				'std' => '',
				'type' => 'qna'
			);

			// Favicons ============================================

		}/* ThemeistsExportImportChemistryData_faqs() */

		
	}/* class ThemeistsExportImportChemistryData */

endif;


//And so it begins
$themeists_exportimportchemistrydata = new ThemeistsExportImportChemistryData;