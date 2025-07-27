<?php
	require "config.php";

	if(!isset($_SESSION["user_id"]))
	{
		header("Location: index.php");exit;
	}
    $nodes = $pdo->query('SELECT id, username FROM users')->fetchAll();
    $edges = $pdo->query('SELECT from_id, to_id, type FROM relationships')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gossip Chain</title>
    <script src="https://unpkg.com/vis-network@latest/standalone/umd/vis-network.min.js"></script>
    <link  href="https://unpkg.com/vis-network@latest/styles/vis-network.min.css" rel="stylesheet" type="text/css">
        <style>
            body
            {
                font-family: system-ui, sans-serif;
                background-color: lightgreen;
            }
            html, body
            {
                margin: 0;
                padding: 0;
                height: 100%;
                overflow: hidden;
            }
            #graph
            {
                width: 100vw;
                height: 100vh;
                border: none;
            }
            .box
            {
                max-width: 400px;
                margin: 6rem auto;
                padding: 1.5rem 2rem;
                border-radius: 5px;
                box-shadow: 0 2px 8px #000200;
            }
            label
            {
                display: block;
                margin: 0.75rem 0;
                margin-bottom: 0.5rem;
            }
            input
            {
                width: 100%;
                padding: 0.5rem;
            }
            button
            {
                border-radius: 5px;
                cursor: pointer;
                background-color: lightyellow;
                width: 100%;
                padding: 0.5rem;
                margin-left: 0.5rem;
            }
            hr
            {
                margin: 2rem 0;
                border: none;
                border-top: 1px solid black;   
            }
            .right
            {
                text-align: right;
                margin: 5rem 2rem 0 0;
            }
            #top_bar
            {
                background-color: lightyellow;
                padding: 1rem;
                height: 30px;
            }
            #graph
            {
                height: calc(100vh - 30px);
            }
        </style>
</head>
<body>
    <div id="top_bar">
        Hello, <?php echo htmlspecialchars($_SESSION["username"]);?><br>
        <a href="logout.php">Log Out</a>
    </div>
    <div id="graph"></div>
    <script>
        console.log("Nodes:", <?php echo json_encode($nodes); ?>);
        console.log("Edges:", <?php echo json_encode($edges); ?>);
        const nodes = new vis.DataSet(<?php echo json_encode(array_map(fn($u)=>["id"=>$u["id"], "label"=>$u["username"]], $nodes));?>);
        const edges = new vis.DataSet(<?php echo json_encode(array_map(fn($e)=>["from"=>$e["from_id"], "to"=>$e["to_id"], "label"=>$e["type"], "arrows"=>""], $edges));?>);
        const network = new vis.Network(document.getElementById("graph"), {nodes, edges}, {physics:{enabled:true, barnesHut:{springLength: 150}}, interaction:{hover:true}});
        network.fit();
        window.addEventListener("resize", ()=>network.fit());
        const user_id = <?= (int) $_SESSION["user_id"];?>;
        network.once("stabilizationIterationsDone", function(){
            network.focus(user_id, {
                scale:1.5,
                animation:{
                    duration: 800,
                    easingFunction: "easeInOutQuad"
                }
            });
        });
    </script>
</body>
</html>