<?php

use Phphub\Core\CreatorListener;
use Phphub\Forms\TopicCreationForm;

class TopicsController extends \BaseController implements CreatorListener
{
    protected $topic;

	public function __construct(Topic $topic)
    {
    	parent::__construct();

        $this->beforeFilter('auth', ['except' => ['index', 'show']]);
        $this->topic = $topic;
    }

	public function index()
	{
		$filter = $this->topic->present()->getTopicFilter();
		$topics = $this->topic->getTopicsWithFilter($filter);
		$nodes  = Node::allLevelUp();
		$links  = Link::remember(1440)->get();

		return View::make('topics.index', compact('topics', 'nodes', 'links'));
	}

	public function create()
	{
		$node = Node::find(Input::get('node_id'));
		$nodes = Node::allLevelUp();

		return View::make('topics.create_edit', compact('nodes', 'node'));
	}

	public function store()
	{
		return App::make('Phphub\Creators\TopicCreator')->create($this, Input::except('_token'));
	}

	public function show($id)
	{
		$topic = Topic::findOrFail($id);
		$replies = $topic->getRepliesWithLimit();
		$node = $topic->node;
		$nodeTopics = $topic->getSameNodeTopics();

        $topic->increment('view_count', 1);

		return View::make('topics.show', compact('topic', 'replies', 'nodeTopics', 'node'));
	}

	public function edit($id)
	{
		$topic = Topic::findOrFail($id);
		$this->authorOrAdminPermissioinRequire($topic->user_id);
		$nodes = Node::allLevelUp();
		$node = $topic->node;

        $topic->body = $topic->body_original;

		return View::make('topics.create_edit', compact('topic', 'nodes', 'node'));
	}

	public function update($id)
	{
		$topic = Topic::findOrFail($id);
		$data = Input::only('title', 'body', 'node_id');

		$this->authorOrAdminPermissioinRequire($topic->user_id);

        $markdown = new Markdown;
        $data['body_original'] = $data['body'];
        $data['body'] = $markdown->convertMarkdownToHtml($data['body']);
        $data['excerpt'] = Topic::makeExcerpt($data['body']);

        // Validation
		App::make('Phphub\Forms\TopicCreationForm')->validate($data);

		$topic->update($data);

		Flash::success(trans('template.Operation succed.'));
		return Redirect::route('topics.show', $topic->id);
	}

    /**
     * ----------------------------------------
     * User Topic Vote function
     * ----------------------------------------
     */

	public function upvote($id)
	{
		$topic = Topic::find($id);
		App::make('Phphub\Vote\Voter')->topicUpVote($topic);
		return Redirect::back();
	}

	public function downvote($id)
	{
		$topic = Topic::find($id);
		App::make('Phphub\Vote\Voter')->topicDownVote($topic);
		return Redirect::back();
	}

    /**
     * ----------------------------------------
     * Admin Topic Management
     * ----------------------------------------
     */

	public function recomend($id)
	{
		$topic = Topic::findOrFail($id);
		$topic->is_excellent = (!$topic->is_excellent);
		$topic->save();

		Flash::success(trans('template.Operation succed.'));

		return Redirect::route('topics.show', $topic->id);
	}

	public function wiki($id)
	{
		$topic = Topic::findOrFail($id);
		$topic->is_wiki = (!$topic->is_wiki);
		$topic->save();

		Flash::success(trans('template.Operation succed.'));

		return Redirect::route('topics.show', $topic->id);
	}

	public function pin($id)
	{
		$topic = Topic::findOrFail($id);
		($topic->order > 0) ? $topic->decrement('order', 1) : $topic->increment('order', 1);
		return Redirect::route('topics.show', $topic->id);
	}

	public function delete($id)
	{
		$topic = Topic::find($id);
		$topic->delete();
		Flash::success(trans('template.Operation succed.'));

		return Redirect::route('topics.index');
	}

    /**
     * ----------------------------------------
     * CreatorListener Delegate
     * ----------------------------------------
     */

    public function creatorFailed($errors)
    {
        return Redirect::to('/');
    }

    public function creatorSucceed($topic)
    {
        Flash::success(trans('template.Operation succed.'));

        return Redirect::route('topics.show', array($topic->id));
    }

}
