<?php
# Mantis - a php based bugtracking system

# Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
# Copyright (C) 2002 - 2008  Mantis Team   - mantisbt-dev@lists.sourceforge.net

# Mantis is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# Mantis is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Mantis.  If not, see <http://www.gnu.org/licenses/>.

	# freemind export API
	# see https://freemind.sourceforge.net
	# it works with freemind version 0.8.0, 0.8.1
	#
	# Copyright (C) 2006-2008 Peter Tandler - http://www.digital-moderation.com

	# --------------------------------------------------------
	# $Id: freemind_api.php 6824 2008-01-19 20:09:34Z tandler $
	# --------------------------------------------------------

	require_once( 'core.php' );
	$t_core_path = config_get( 'core_path' );
	require_once( $t_core_path . 'filter_api.php' );
	require_once( $t_core_path . 'csv_api.php' );
	require_once( $t_core_path . 'bug_api.php' );
	require_once( $t_core_path . 'columns_api.php' );

	/**
	 * export issues as freemind mind map
	 */

	function freemind_export_map() {

		$t_page_number = 1;
		$t_per_page = -1;
		$t_bug_count = null;
		$t_page_count = null;

		$t_nl = csv_get_newline();
		// $t_nl = "\n";

		# Get bug rows according to the current filter
		$t_rows = filter_get_bug_rows( $t_page_number, $t_per_page, $t_page_count, $t_bug_count );

		if ( $t_rows === false ) {
			$t_rows = array();
		}

		$t_bug_list = freemind_find_descendents($t_rows);

		echo '<map version="0.8.0">', $t_nl;
		echo '<!-- mantis export -->', $t_nl;
		echo '<node TEXT="', freemind_note_display(helper_get_default_export_filename('')), '">', $t_nl;
		echo '<node TEXT="Issues">', $t_nl;
		echo '<font BOLD="true" NAME="SansSerif" SIZE="12"/>', $t_nl;
		echo $t_nl;

		# export the rows
		foreach ( $t_bug_list as $t_bug ) {
			if ( count( $t_bug->parents ) === 0 ) {
				freemind_export_bug( $t_bug, $t_bug_list );
			}
		}

		echo '</node>', $t_nl;

		# @@@ maybe add nodes for each tag pointing to all related issues

		echo '</node>', $t_nl;
		echo '</map>', $t_nl;
	}

	/**
	 * write the bug and all of its descendants (children) as mm nodes
	 */
	function freemind_export_bug($p_bug, &$p_bug_list) {
		$p_bug = freemind_prepare_export( $p_bug );
		$t_id = $p_bug->id;
		$t_summary = $p_bug->summary;
		$t_handler = $p_bug->handler;
		$t_reporter = $p_bug->reporter;
		$t_project = $p_bug->project;
		$t_category = $p_bug->category;
		$t_reporter = $p_bug->reporter;
		$t_nl = csv_get_newline();

		echo "<node ID=\"Freemind_Link_$t_id\" ",
				"TEXT=\"$t_id: $t_summary [$t_project / $t_category]\" ",
				"LINK=\"",
				string_get_bug_view_url_with_fqdn($t_id), "\" ",
				">$t_nl";

		if ( $p_bug->status >= CLOSED ) {
			echo '<icon BUILTIN="button_ok"/>', $t_nl;
		} else if ( $p_bug->status >= RESOLVED ) {
			echo '<icon BUILTIN="button_ok"/>', $t_nl;
		} else if ( $p_bug->status >= ASSIGNED ) {
			echo '<icon BUILTIN="pencil"/>', $t_nl;
		} else { # NEW_
			echo '<icon BUILTIN="bookmark"/>', $t_nl;
		}

		echo "<hook NAME=\"accessories/plugins/NodeNote.properties\"><text>";
		echo "Reporter: ", $t_reporter, $t_nl;

		if ( $p_bug->handler_id != NO_USER ) {
			echo "Assigned To: ", $t_handler, $t_nl;
		}

		echo $t_nl;

		if ( $p_bug->description != '' ) {
			echo $p_bug->description, $t_nl;
		}

		if ( $p_bug->steps_to_reproduce != '' ) {
			echo $t_nl, "----------", $t_nl, "Steps to reproduce:", $t_nl, $t_nl;
			echo $p_bug->steps_to_reproduce,$t_nl;
		}

		if ( $p_bug->additional_information != '' ) {
			echo $t_nl, "----------", $t_nl, "Additional information:", $t_nl, $t_nl;
			echo $p_bug->additional_information, $t_nl;
		}

		echo "</text></hook>$t_nl";

		# add sub nodes and arrows for relationships
		if ( ! empty($p_bug->related_to) ) {
			echo "\t<node TEXT=\"related\">$t_nl";
			foreach( $p_bug->related_to as $t_rel_id ) {
				$t_rel_link="#Freemind_Link_$t_rel_id";

				# if related issue is not included, add link to mantis instead of relative link
				if ( ! $p_bug_list[$t_rel_id] ) {
					$t_rel_link=string_get_bug_view_url_with_fqdn($t_rel_id);
				}

				echo "\t<node TEXT=\"$t_rel_id: ", $p_bug_list[$t_rel_id]->summary, "\"",
					" LINK=\"$t_rel_link\"",
					" >$t_nl";
				echo "\t<arrowlink DESTINATION=\"Freemind_Link_$t_rel_id\" ENDARROW=\"Default\" ENDINCLINATION=\"24;0;\" ID=\"Freemind_Arrow_Link_$t_id$t_rel_id\" STARTARROW=\"None\" STARTINCLINATION=\"24;0;\"/>$t_nl";
				echo "\t</node>$t_nl";
			}
			echo "\t</node>$t_nl";
		}

		# look for attached files and include a subnode "files" that contains links to all files
		if ( file_bug_has_attachments($t_id) ) {
			echo "\t<node TEXT=\"attachments\">$t_nl";
			foreach( file_get_visible_attachments($t_id) as $t_attachment ) {
				echo "\t\t<node TEXT=\"", $t_attachment['display_name'], "\"",
					" LINK=\"", config_get( 'path' ), htmlspecialchars($t_attachment['download_url'], ENT_COMPAT, 'utf-8'), "\"", // todo: or should I use string_html_specialchars instead of htmlspecialchars? Seems this one removes double encoded special chars (similar to double_encode=false?
					"/>$t_nl";
			}
			echo "\t</node>$t_nl";
		}

		#@@@ look for all links in summary, description, ... and create additional link subnotes for each

		# Now put all children here
		foreach( $p_bug->children as $t_child_id ) {
			freemind_export_bug( $p_bug_list[$t_child_id], $p_bug_list );
		}
		echo "</node>$t_nl$t_nl";
	}

	/**
	 * convert to a string that can be written to a mm note (hook)
	 */
	function freemind_note_display( $p_string ) {
		$p_string = string_strip_hrefs( $p_string );
		$p_string = string_html_specialchars( $p_string );
		$p_string = preg_replace( "/\n/", "&#xa;", $p_string );
		return $p_string;
	}

	/**
	 * convert all fields of the bug to a string that can be exported as mm
	 */
	function freemind_prepare_export( $p_bug_data ) {
		$p_bug_data->category			= freemind_note_display(
				category_full_name( $p_bug_data->category_id, false ) );
		$p_bug_data->date_submitted		= freemind_note_display( $p_bug_data->date_submitted );
		$p_bug_data->last_updated		= freemind_note_display( $p_bug_data->last_updated );
		$p_bug_data->os				= freemind_note_display( $p_bug_data->os );
		$p_bug_data->os_build			= freemind_note_display( $p_bug_data->os_build );
		$p_bug_data->platform			= freemind_note_display( $p_bug_data->platform );
		$p_bug_data->version			= freemind_note_display( $p_bug_data->version );
		$p_bug_data->build			= freemind_note_display( $p_bug_data->build );
		$p_bug_data->fixed_in_version		= freemind_note_display( $p_bug_data->fixed_in_version );
		$p_bug_data->summary			= freemind_note_display( $p_bug_data->summary );
		$p_bug_data->sponsorship_total		= freemind_note_display( $p_bug_data->sponsorship_total );
		$p_bug_data->sticky			= freemind_note_display( $p_bug_data->sticky );

		$p_bug_data->description		= freemind_note_display( $p_bug_data->description );
		$p_bug_data->steps_to_reproduce		= freemind_note_display( $p_bug_data->steps_to_reproduce );
		$p_bug_data->additional_information	= freemind_note_display( $p_bug_data->additional_information );

		$p_bug_data->handler			= user_get_name( $p_bug_data->handler_id );
		$p_bug_data->reporter			= user_get_name( $p_bug_data->reporter_id );
		$p_bug_data->project			= project_get_field( $p_bug_data->project_id, 'name' );
		$p_bug_data->reproducibility		= get_enum_element( 'reproducibility', $p_bug_data->reproducibility );

		return $p_bug_data;
	}

	/**
	 * go through all bugs in the filtered bug list and find their parents and children.
	 * this is used to be able to find the "root" nodes (without parents) and to be
	 * able to export the children as subnodes in mm
	 */
	function freemind_find_descendents( &$p_rows ) {

		# List of visited issues and their data.
		$v_bug_list = array ( );

		foreach( $p_rows as $t_row ) {
			$t_bug_id = $t_row->id;

			$t_bug = bug_get( $t_bug_id, true );
			$v_bug_list[$t_bug_id] = $t_bug;
			$v_bug_list[$t_bug_id]->id = $t_bug_id;
			$v_bug_list[$t_bug_id]->parents = array ( );
			$v_bug_list[$t_bug_id]->children = array ( );
			$v_bug_list[$t_bug_id]->related_to = array ( );
		}

		foreach( array_keys($v_bug_list) as $t_bug_id ) {
			$t_relationships = relationship_get_all_dest( $t_bug_id );
			foreach ( $t_relationships as $t_relationship ) {
				if ( $t_relationship->type == BUG_DEPENDANT ) {
					if ( array_key_exists( $t_relationship->src_bug_id, $v_bug_list ) ) {
						array_push( $v_bug_list[$t_bug_id]->parents, $t_relationship->src_bug_id );
					}
				} else if ( $t_relationship->type == BUG_RELATED ) {
					array_push( $v_bug_list[$t_bug_id]->related_to, $t_relationship->src_bug_id );
				}
			}

			$t_relationships = relationship_get_all_src( $t_bug_id );
			foreach ( $t_relationships as $t_relationship ) {
				if ( $t_relationship->type == BUG_DEPENDANT ) {
					if ( array_key_exists( $t_relationship->dest_bug_id, $v_bug_list ) ) {
						#### is this the same as push?
						$v_bug_list[$t_bug_id]->children[] = $t_relationship->dest_bug_id;
					}
				} else if ( $t_relationship->type == BUG_RELATED ) {
					array_push( $v_bug_list[$t_bug_id]->related_to, $t_relationship->dest_bug_id );
				}
			}
		}
		return $v_bug_list;
	}


?>
