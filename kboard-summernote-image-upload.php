<?php
add_action('kboard_skin_header', 'kboard_summernote_image_upload_skin_header', 10, 1);
function kboard_summernote_image_upload_skin_header($builder){
	$board = $builder->board;
	if($board->use_editor == 'snote' && kboard_builder_mod() == 'editor'){
		?>
		<script>
		jQuery(document).ready(function(){
			if(kboard_current.use_editor == 'snote'){ // summernote
				jQuery('.summernote').each(function(){
					var height = parseInt(jQuery(this).height());
					var placeholder = jQuery(this).attr('placeholder');
					var lang = 'en-US';
					
					if(kboard_settings.locale == 'ko_KR'){
						lang = 'ko-KR';
					}
					else if(kboard_settings.locale == 'ja'){
						lang = 'ja-JP';
					}
					
					jQuery(this).summernote({
						toolbar: [
								['style', ['style']],
								['fontsize', ['fontsize']],
								['font', ['bold', 'italic', 'underline', 'clear']],
								['fontname', ['fontname']],
								['color', ['color']],
								['para', ['ul', 'ol', 'paragraph']],
								['height', ['height']],
								['table', ['table']],
								['insert', ['link', 'picture', 'hr']],
								['view', ['fullscreen', 'codeview']],
								['help', ['help']]
						],
						fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica Neue', 'Helvetica', 'Impact', 'Lucida Grande', 'Tahoma', 'Times New Roman', 'Verdana', 'Nanum Gothic', 'Malgun Gothic', 'Noto Sans KR', 'Apple SD Gothic Neo'],
						fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica Neue', 'Helvetica', 'Impact', 'Lucida Grande', 'Tahoma', 'Times New Roman', 'Verdana', 'Nanum Gothic', 'Malgun Gothic', 'Noto Sans KR', 'Apple SD Gothic Neo'],
						fontSizes: ['8','9','10','11','12','13','14','15','16','17','18','19','20','24','30','36','48','64','82','150'],
						lang: lang,
						height: height,
						placeholder: placeholder,
						callbacks: {
							onImageUpload : function(files){
								kboard_summernote_image_upload(files, this);
							},
							onPaste: function(e){
								var clipboardData = e.originalEvent.clipboardData;
								if(clipboardData && clipboardData.items && clipboardData.items.length){
									var item = clipboardData.items[0];
									if(item.kind === 'file' && item.type.indexOf('image/') !== -1){
										e.preventDefault();
									}
								}
							}
						}
					});
				});
			}
		});
		
		function kboard_summernote_image_upload(files, editor){
			data = new FormData();
			data.append('action', 'kboard_summernote_image_upload');
			data.append('board_id', '<?php echo $board->id?>');
			data.append('content_id', '<?php echo kboard_uid()?>');
			
			for(var i=0; i<files.length; i++){
				data.append('kboard_media_file[]', files[i]);
			}
			
			jQuery.ajax({
				data : data,
				type : 'POST',
				url : kboard_settings.ajax_url,
				contentType : false,
				processData : false,
				success : function(data){
					for(var i=0; i<data.length; i++){
						jQuery('#kboard_content').summernote('editor.pasteHTML', '<img src="'+data[i]['url']+'">');
						if(!jQuery('input[value="'+data[i]['media_group']+'"]').length){
							jQuery('.kboard-form').prepend('<input type="hidden" name="kboard_summernote_media_group[]" value="'+data[i]['media_group']+'">');
						}
					}
				}
			});
		}
		</script>
		<?php
	}
}

add_action('wp_ajax_kboard_summernote_image_upload', 'kboard_summernote_image_upload');
add_action('wp_ajax_nopriv_kboard_summernote_image_upload', 'kboard_summernote_image_upload');
function kboard_summernote_image_upload(){
	global $wpdb;
	
	$media = new KBContentMedia();
	$media->board_id = intval(isset($_POST['board_id'])?$_POST['board_id']:'');
	$media->media_group = kboard_media_group();
	$media->content_uid = intval(isset($_POST['content_uid'])?$_POST['content_uid']:'');
	$media->upload();
	
	$media_list = $media->getList();
	
	$list = array();
	foreach($media_list as $index=>$item){
		$list[$index]['url'] = site_url($item->file_path, 'relative');
		$list[$index]['media_group'] = $media->media_group;
	}
	
	wp_send_json($list);
}

add_action('kboard_document_insert', 'kboard_summernote_image_upload_check', 10, 4);
add_action('kboard_document_update', 'kboard_summernote_image_upload_check', 10, 4);
function kboard_summernote_image_upload_check($content_uid, $board_id, $content, $board){
	global $wpdb;
	
	$media_group = isset($_POST['kboard_summernote_media_group']) ? $_POST['kboard_summernote_media_group'] : array();
	$media_group = array_map('sanitize_text_field', $media_group);
	
	if($board->use_editor == 'snote' && $media_group){
		$media = new KBContentMedia();
		$media_list = array();
		
		foreach($media_group as $media_item){
			$media_item = esc_sql($media_item);
			$results = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}kboard_meida` WHERE `media_group`='{$media_item}'");
			
			foreach($results as $item){
				if(strpos($content->content, $item->file_path) === false){
					$media->deleteWithMediaUID($item->uid);
				}
				else{
					$media_list[] = $item->media_group;
				}
			}
		}
		
		$content->option->summernote_image_uid = $media_list;
	}
}

add_action('kboard_skin_editor_header_before', 'kboard_summernote_image_upload_editor_header_before', 10, 2);
function kboard_summernote_image_upload_editor_header_before($content, $board){
	if($board->use_editor == 'snote' && $content->option->summernote_image_uid){
		if(is_array($content->option->summernote_image_uid)){
			foreach($content->option->summernote_image_uid as $image_uid){
				?>
				<input type="hidden" name="kboard_summernote_media_group[]" value="<?php echo $image_uid?>">
				<?php
			}
		}
		else{
			?>
			<input type="hidden" name="kboard_summernote_media_group[]" value="<?php echo $content->option->summernote_image_uid?>">
			<?php
		}
	}
}