<?php
require 'bootstrap.php';

$client = new couchClient(COUCH_DSN, COUCH_DBNAME);
$mem = new Memcache();
$mem->connect(MEM_HOST, MEM_PORT);

// key cache
$key_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$key_date = sha1('dates');

// get cache
$from_cache_url = $mem->get($key_url);
$from_cache_date = $mem->get($key_date);

// result cache;
$results = [];
$dates = '-';

try {
	$table = null;
	$head_title =  null;

	$options = array(
		'include_docs' => true,
		'limit' => 99999,
		'descending' => true
	);
	$response = $client->setQueryParameters($options);
	$get_posts = $response->getView('get_blog_posts', 'by_date');
	$query_string = isset($_GET['action']) ? $_GET['action'] : null;

	switch ($query_string) {
		case 'edit':
			// update doc
			if (isset($_POST['update'])) {
				$get_doc = $client->getDoc($_GET['docid']);
				$get_doc->title = $_POST['title'];
				$get_doc->body = $_POST['body'];
				$get_doc->category = $_POST['category'];
				$d_res = (object)$get_doc;
				$client->storeDoc($d_res);
				header('Location:index.php');
			}

			$doc = $client->getDoc($_GET['docid']);
			$head_title .= 'Edit';

			$is_selected_hot = $doc->category === 'hot' ? 'selected="selected"': '';
			$is_selected_news = $doc->category === 'news' ? 'selected="selected"': '';
			$is_selected_tech = $doc->category === 'tech' ? 'selected="selected"': '';

			$table .= <<<table
			<form method="post">
			<table border=1>
				<tr>
					<th>Title</th>
					<td><input type="text" style="width:200px;" class="form" name="title" value="$doc->title"></td>
				<tr>
				<tr>
					<th>Body</th>
					<td><textarea style="width:350px; height:100px;" name="body" class="form">$doc->body</textarea></td>
				<tr>
				<tr>
					<th>Category</th>
					<td>
						<select name="category" class="form" style="width:100px">
							<option value="hot" $is_selected_hot>Hot</option>
							<option value="news" $is_selected_news>News</option>
							<option value="tech" $is_selected_tech>Tech</option>
						</select>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><input type="submit" name="update" value="Update"></td>
				<tr>
			</table>
			</form>
table;
			break;

		case 'delete':
			// delete doc
			if (isset($_GET['docid'])) {
				$doc = $client->getDoc($_GET['docid']);
				if ($client->deleteDoc($doc)) {
					header('Location:index.php');
				}
			}
			break;

		case 'create':
			// insert doc
			if (isset($_POST['submit'])) {
				$d = new stdClass;
				$d->title = $_POST['title'];
				$d->body = $_POST['body'];
				$d->category = $_POST['category'];
				$d->created_at = time();
				$d_res = (object)$d;
				$client->storeDoc($d_res);
				header('Location:index.php');
			}

			$head_title .= 'Create post';
			$table .= <<<table
			<form action="?action=create" method="post">
				<table border=1>
					<tr>
						<th>Title</th>
						<td>
							<input type="text" style="width:200px;" class="form" name="title">
						</td>
					</tr>
					<tr>
						<th>Body</th>
						<td>
							<textarea style="width:350px; height:100px;" class="form" name="body"></textarea>
						</td>
					</tr>
					<tr>
						<th>Category</th>
						<td>
							<select name="category" class="form" style="width:100px">
								<option value="tech">Tech</option>
								<option value="news">News</option>
								<option value="hot">Hot</option>
							</select>
						</td>
					</tr>
					<tr>
						<td></td>
						<td><input type="submit" name="submit" value="submit"></td>
					</tr>
				</table>
			</form>
table;
			break;

		default:

			if ($from_cache_url && $from_cache_date) {
				$results = $from_cache_url;
				$dates = $from_cache_date;
			}
			else {
				foreach ($get_posts->rows as $k => $v) { 
					$results[] = $v; 
					$mem->set($key_url, $results, false, 60); // 1 minute
					$mem->set($key_date, date('Y-m-d H:i:s'), false, 60);
				}
			}
			
			// display doc
			$head_title .= 'Last updates';
			foreach ($results as $k => $v) {
				$created_at = date('d-M-Y', $v->doc->created_at);
				$title = $v->doc->title;
				$body = $v->doc->body;
				$category = $v->doc->category;
				$docid = $v->doc->_id;

				$table .= <<<table
					<h3 style="color:#555">$title
					<small style="font-size:10px"><a href="?action=edit&docid=$docid">Edit</a> | <a href="?action=delete&docid=$docid">Delete</a></small>
					</h3>
					<p>$created_at | $category</p>
					<p>$body</p>
table;
				$k++;
			}
			break;
	} // eof switch

	$tables = sprintf('<h3>%s</h3>'.$table, $head_title);
}
catch(Exception $e) {
	echo sprintf('Error Processing Request: %s', $e->getMessage());
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
    <title>My Blog</title>
    <link rel="stylesheet" href="css/reset.css" />
	<link rel="stylesheet" href="css/text.css" />
	<link rel="stylesheet" href="css/960.css" />
	<link rel="stylesheet" href="css/base.css" /
</head>
<body>
	<div class="container_12">
		<h2 style="color:blue">My Blog</h2>
		<p><small><code><b>Latest update from cache: <?= $dates; ?></b></code></small></p>
		<div style="float:right">
			<form method="get">
			<input type="text" name="query" placeholder="Search">
			<input type="submit" name="s" value="Search">
			</form>
		</div>
		<div class="grid_3">
			<ul>
				<li><a href="?action=create">Create post</a></li>
				<li><a href="index.php">List post</a></li>
			</ul>
		</div>
		<div class="grid_9"><?= $tables; ?></div>
	</div>
</body>
<html>