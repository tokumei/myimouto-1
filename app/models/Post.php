<?php
foreach (glob(dirname(__FILE__).'/Post/*.php') as $trait) require $trait;

class Post extends Rails\ActiveRecord\Base
{
    use PostSqlMethods, PostCommentMethods, PostImageStoreMethods,
        PostVoteMethods, PostTagMethods, PostCountMethods,
        Post\CacheMethods, PostParentMethods, PostFileMethods,
        PostChangeSequenceMethods, PostRatingMethods, PostStatusMethods,
        PostApiMethods, /*PostMirrorMethods, */PostFrameMethods;
    
    use Moebooru\Versioning\VersioningTrait;
    
    protected $previous_id;
    
    protected $next_id;
    
    public $updater_user_id;
    
    public $updater_ip_addr;
    
    static public function init_versioning($v)
    {
        $v->versioned_attributes([
            'source' => ['default' => ''],
            'cached_tags',
            # MI: Allowing reverting to default.
            'is_shown_in_index' => ['default' => true, 'allow_reverting_to_default' => true],
            'rating',
            'is_rating_locked' => ['default' => false],
            'is_note_locked' => ['default' => false],
            'parent_id' => ['default' => null],
            // iTODO: uncomment when frames are enabled
            // 'frames_pending' => ['default' => '', 'allow_reverting_to_default' => true]
        ]);
    }
    
    public function __call($method, $params)
    {
        switch(true) {
            # Checking status: $paramsost->is_pending();
            case (strpos($method, 'is_') === 0):
                $status = str_replace('is_', '', $method);
                return $this->status == $status;
            default:
                return parent::__call($method, $params);
        }
    }

    public function next_id()
    {
        if ($this->next_id === null) {
            $post = Post::available()->where('id > ?', $this->id)->limit(1)->first();
            $this->next_id = $post ? $post->id : false;
        }
        return $this->next_id;
    }

    public function previous_id()
    {
        if ($this->previous_id === null) {
            $post = Post::available()->where('id < ?', $this->id)->order('id DESC')->limit(1)->first();
            $this->previous_id = $post ? $post->id : false;
        }
        return $this->previous_id;
    }
    
    public function author()
    {
        return $this->user ? $this->user->name : null;
    }
    
    public function can_be_seen_by($user = null, array $options = array())
    {
        if (empty($options['show_deleted']) && $this->status == 'deleted') {
            return false;
        }
        
        return CONFIG()->can_see_post($user, $this);
    }
    
    public function normalized_source()
    {
        if (preg_match('/pixiv\.net\/img/', $this->source)) {
            if (preg_match('/(\d+)(_s|_m|(_big)?_p\d+)?\.\w+(\?\d+)?\z/', $this->source, $m))
                $img_id = $m[1];
            else
                $img_id = null;
            return "http://www.pixiv.net/member_illust.php?mode=medium&illust_id=" . $img_id;
        } elseif (strpos($this->source, 'http://') === 0 || strpos($this->source, 'https://') === 0)
            return $this->source;
        else
            return 'http://' . $this->source;
    }
    
    public function clear_avatars()
    {
        User::clear_avatars($this->id);
    }
    
    public function approve($approver_id)
    {
        $old_status = $this->status;

        if ($this->flag_detail)
            $this->flag_detail->updateAttribute('is_resolved', true);
        
        $this->updateAttributes(array('status' => 'active', 'approver_id' => $approver_id));

        # Don't bump posts if the status wasn't "pending"; it might be "flagged".
        if ($old_status == 'pending' and CONFIG()->hide_pending_posts) {
            // $this->touch_index_timestamp();
            $this->save();
        }
    }
    
    public function voted_by($score = null)
    {
        # Cache results
        if (!$this->voted_by) {
            foreach (range(1, 3) as $v) {
                $this->voted_by[$v] =
                    User::where("v.post_id = ? and v.score = ?", $this->id, $v)
                        ->joins("JOIN post_votes v ON v.user_id = users.id")
                        ->select("users.name, users.id")
                        ->order("v.updated_at DESC")
                        ->take()
                        ->getAttributes(['id', 'name']) ?: array();
            }
        }
        
        if (func_num_args())
            return $this->voted_by[$score];
        return $this->voted_by;
    }

    public function can_user_delete(User $user = null)
    {
        if (!$user)
            $user = current_user();
        
        if (!$user->has_permission($this))
            return false;
        elseif (!$user->is_mod_or_higher() && !$this->is_held() && (strtotime(date('Y-m-d H:i:s')) - strtotime($this->created_at)) > 60*60*24)
            return false;
        
        return true;
    }
    
    public function favorited_by()
    {
        return $this->voted_by(3);
    }
    
    public function active_notes()
    {
        return $this->notes ? $this->notes->select(function($x){return $x->is_active;}) : array();
    }
    
    public function set_flag_detail($reason, $creator_id)
    {
        if ($this->flag_detail) {
            $this->flag_detail->updateAttributes(array('reason' => $reason, 'user_id' => $creator_id, 'created_at' => date('Y-m-d H:i:s')));
        } else {
            FlaggedPostDetail::create(array('post_id' => $this->id, 'reason' => $reason, 'user_id' => $creator_id, 'is_resolved' => false));
        }
    }
    
    public function flag($reason, $creator_id)
    {
        $this->updateAttribute('status', 'flagged');
        $this->set_flag_detail($reason, $creator_id);
    }
    
    public function destroy_with_reason($reason, $current_user)
    {
        // Post.transaction do
        if ($this->flag_detail)
            $this->flag_detail->updateAttribute('is_resolved', true);
        $this->flag($reason, $current_user->id);
        $this->first_delete();
        
        if (CONFIG()->delete_posts_permanently)
            $this->delete_from_database();
        // end
    }

    static public function static_destroy_with_reason($id, $reason, $current_user)
    {
        $post = Post::find($id);
        return $post->destroy_with_reason($reason, $current_user);
    }

    public function first_delete()
    {
        $this->runCallbacks('delete', function() {
            $this->updateAttributes(array('status' => 'deleted'));
        });
    }

    public function delete_from_database()
    {
        $this->runCallbacks('destroy', function() {
            $this->delete_file();
            self::connection()->executeSql('UPDATE pools SET post_count = post_count - 1 WHERE id IN (SELECT pool_id FROM pools_posts WHERE post_id = ?)', $this->id);
            self::connection()->executeSql('UPDATE tags SET post_count = post_count - 1 WHERE id IN (SELECT tag_id FROM posts_tags WHERE post_id = ?)', $this->id);
            # MI: Destroying pool posts manually so their histories are deleted by foreign keys.
            # This is done in Pool too. This could be done with a MySQL trigger.
            PoolPost::destroyAll('post_id = ?', $this->id);
            self::connection()->executeSql("DELETE FROM posts WHERE id = ?", $this->id);
        });
    }
    
    public function undelete()
    {
        if ($this->status == 'active') {
            return;
        }
        $this->runCallbacks('undelete', function() {
            $this->updateAttributes(['status' => 'active']);
        });
    }
    
    public function service()
    {
        return CONFIG()->local_image_service;
    }
    
    public function service_icon()
    {
        return "/favicon.ico";
    }
    
    protected function callbacks()
    {
        return [
            'before_create' => ['set_index_timestamp'],
            'after_create'  => ['after_creation'],
            
            'before_delete' => ['clear_avatars'],
            'after_delete'  => ['give_favorites_to_parent', 'decrement_count'],
            
            'after_undelete'=> ['increment_count'],
            
            'before_save'   => ['commit_tags', 'filter_parent_id'],
            'after_save'    => ['update_parent', 'save_post_history', 'expire_cache'],
            
            'after_destroy' => ['expire_cache'],
            
            'before_validation_on_create' => [
                'download_source', 'ensure_tempfile_exists', 'determine_content_type',
                'validate_content_type', 'validate_video_stream', 'generate_hash', 'set_image_dimensions',
                'set_image_status', 'check_pending_count', 'generate_sample',
                'generate_jpeg', 'generate_preview', 'move_file'
            ],
            'after_validation_on_create'  => ['before_creation']
        ];
    }
    
    protected function associations()
    {
        return [
            'has_one'    => ['flag_detail' => ['class_name' => "FlaggedPostDetail"]],
            'belongs_to' => [
                'user',
                'approver' => ['class_name' => 'User']
            ],
            'has_many' => [
                'notes'       => [function() { $this->order('id DESC')->where('is_active = 1'); }],
                'comments'    => [function() { $this->order("id"); }],
                'children'    => [
                    function() { $this->order('id')->where("status != 'deleted'"); },
                    'class_name' => 'Post',
                    'foreign_key' => 'parent_id'
                ],
                'tag_history' => [function() { $this->order("id DESC"); }, 'class_name' => 'PostTagHistory'],
            ]
        ];
    }
    
    protected function before_creation()
    {
        $this->upload = !empty($_FILES['post']['tmp_name']['file']) ? true : false;
        
        if (CONFIG()->tags_from_filename)
            $this->get_tags_from_filename();
        if (CONFIG()->source_from_filename)
            $this->get_source_from_filename();
        
        if (!$this->rating)
            $this->rating = CONFIG()->default_rating_upload;
        
        $this->rating = strtolower(substr($this->rating, 0, 1));
        
        if ($this->gif() && CONFIG()->add_gif_tag_to_gif)
            $this->new_tags[] = 'gif';
        elseif ($this->flash() && CONFIG()->add_flash_tag_to_swf)
            $this->new_tags[] = 'flash';
        elseif ($this->video() && CONFIG()->add_video_tag_to_video)
            $this->new_tags[] = 'video';
        
        if ($this->new_tags)
            $this->old_tags = 'tagme';
        
        $this->cached_tags = 'tagme';
        
        !$this->parent_id && $this->parent_id = null;
        !$this->source && $this->source = null;
        
        $this->random = mt_rand();
        
        Tag::find_or_create_by_name('tagme');
    }
    
    protected function after_creation()
    {
        if ($this->new_tags) {
            $this->clearChangedAttributes();
            $this->commit_tags();
            
            $update = [];
            foreach (array_keys($this->changedAttributes()) as $attrName) {
                $update[$attrName] = $this->getAttribute($attrName);
            }
            $this->updateColumns($update);
        }
    }
    
    protected function set_index_timestamp()
    {
        $this->index_timestamp = date('Y-m-d H:i:s');
    }
    
    # Added to avoid SQL constraint errors if parent_id passed isn't a valid post.
    protected function filter_parent_id()
    {
        if (($parent_id = trim($this->parent_id)) && Post::where(['id' => $parent_id])->first())
            $this->parent_id = $parent_id;
        else
            $this->parent_id = null;
    }
    
    protected function scopes()
    {
        return [
            'available' => function() {
                $this->where("posts.status <> ?", "deleted");
            },
            'has_any_tags' => function($tags) {
                // where('posts.tags_index @@ ?', Array(tags).map { |t| t.to_escaped_for_tsquery }.join(' | '))
            },
            'has_all_tags' => function($tags) {
                $this
                    ->joins('INNER JOIN posts_tags pti ON p.id = pti.post_id JOIN tags ti ON pti.tag_id = ti.id')
                    ->where('ti.name IN ('.implode(', ', array_fill(0, count($tags), '?')).')');
                // where('posts.tags_index @@ ?', Array(tags).map { |t| t.to_escaped_for_tsquery }.join(' & '))
            },
            'flagged' => function() {
                $this->where("status = ?", "flagged");
            }
        ];
    }
}