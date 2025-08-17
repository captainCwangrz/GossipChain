<?php
/**
 * Main dashboard displaying the relationship graph.
 */

require 'config.php';

// Redirect unauthenticated users
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch graph nodes and edges
$nodes = $pdo->query('SELECT id, username FROM users')->fetchAll();
$edges = $pdo->query('SELECT from_id, to_id, type FROM relationships')->fetchAll();

// Pending relationship requests for the current user
$stmt = $pdo->prepare(
    'SELECT r.id, r.from_id, r.type, u.username
       FROM requests r JOIN users u ON r.from_id = u.id
      WHERE r.to_id = ? AND r.status = "PENDING"'
);
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();
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
        body            { font-family: system-ui, sans-serif; background-color: lightgreen; }
        html, body      { margin:0; padding:0; height:100%; overflow:hidden; }
        #graph          { width:100vw; height:calc(100vh - 110px); border:none; }
        #top_bar        { background:lightyellow; padding:.5rem 1rem; height:30px; }
        #search_bar     { padding:.25rem 1rem; background:#fff; }
        #search_input   { width:200px; }
        #bottom_bar     { background:lightyellow; min-height:40px; padding:.5rem 1rem; }
        .context-menu   {
            position:absolute; background:#ffffffee; box-shadow:0 2px 6px rgba(0,0,0,.2);
            border-radius:4px; padding:.5rem; display:none; z-index:10;
        }
        .context-menu button {
            display:block; width:100%; background:none; border:none;
            padding:.25rem 0; cursor:pointer; text-align:left;
        }
    </style>
</head>
<body>
    <div id="top_bar">
        Hello, <?php echo htmlspecialchars($_SESSION["username"]);?>
        <a style="float:right" href="logout.php">Log Out</a>
    </div>

    <div id="search_bar">
        <input type="text" id="search_input" placeholder="Search user.">
        <button id="search_btn">Go</button>
    </div>

    <div id="graph"></div>
    <div id="contextMenu" class="context-menu"></div>

    <div id="bottom_bar">
        <?php if($requests): foreach($requests as $r): ?>
            <div style="margin-bottom:4px;">
                <?=htmlspecialchars($r['username'])?> requests <?=htmlspecialchars($r['type'])?>
                <form style="display:inline" method="post" action="relationship.php">
                    <input type="hidden" name="action" value="accept_request">
                    <input type="hidden" name="request_id" value="<?=$r['id']?>">
                    <button type="submit">Accept</button>
                </form>
                <form style="display:inline" method="post" action="relationship.php">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" value="<?=$r['id']?>">
                    <button type="submit">Reject</button>
                </form>
            </div>
        <?php endforeach; else: ?>
            Ready
        <?php endif; ?>
    </div>

    <script>
        /* -------------------------------------------------
           1. Build the vis‑network graph
        --------------------------------------------------*/
        console.log("Nodes:", <?php echo json_encode($nodes); ?>);
        console.log("Edges:", <?php echo json_encode($edges); ?>);

        const nodes  = new vis.DataSet(<?php echo json_encode(array_map(fn($u)=>
                         ["id"=>$u["id"], "label"=>$u["username"]], $nodes)); ?>);
        const edges  = new vis.DataSet(<?php echo json_encode(array_map(fn($e)=>
                         ["from"=>$e["from_id"], "to"=>$e["to_id"], "label"=>$e["type"], "arrows"=>""], $edges)); ?>);
        const options = {
            nodes : { shape:'dot', size:20, font:{color:'white'},
                      color:{ background:'#6b99f7', border:'#3b6dd8',
                              highlight:{background:'#f76b9a', border:'#d43f67'} } },
            edges : { color:{color:'#888', highlight:'#f76b9a'}, width:2 },
            physics:{ enabled:true, barnesHut:{springLength:150} },
            interaction:{ hover:true }
        };

        const network = new vis.Network(document.getElementById("graph"),
                                        {nodes, edges}, options);

        network.fit();
        window.addEventListener("resize", () => network.fit());

        const user_id = <?= (int) $_SESSION["user_id"]; ?>;
        network.once("stabilizationIterationsDone", () => {
            network.focus(user_id, {
                scale:1.5,
                animation:{ duration:800, easingFunction:"easeInOutQuad" }
            });
        });

        /* -------------------------------------------------
           2. Search bar helpers
        --------------------------------------------------*/
        document.getElementById('search_btn').addEventListener('click', search);
        document.getElementById('search_input').addEventListener('keydown', e => {
            if(e.key === 'Enter'){ e.preventDefault(); search(); }
        });

        function search(){
            const term = document.getElementById('search_input')
                           .value.trim().toLowerCase();
            if(!term) return;
            const id = nodes.getIds().find(id =>
                         nodes.get(id).label.toLowerCase() === term);
            if(id) network.focus(id, {scale:1.5, animation:{duration:800}});
        }

        /* -------------------------------------------------
           3. Context‑menu logic (with “justOpened” guard)
        --------------------------------------------------*/
        const menu     = document.getElementById('contextMenu');
        const relTypes = ['DATING','BEST_FRIEND','BROTHER','SISTER','BEEFING','CRUSH'];
        let justOpened = false;        // ← NEW flag

        function optionSelect(id,name){
            return '<select id="'+name+'">'+
                   relTypes.map(t=>`<option value="${t}">${t}</option>`).join('')
                   +'</select>';
        }

        function post(action, extra){
            const formData = new URLSearchParams(extra);
            formData.append('action', action);
            fetch('relationship.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:formData.toString()
            }).then(() => location.reload());
        }

        function sendRequest(id){
            post('send_request', {to_id:id, type:document.getElementById('relType').value});
        }
        function modifyRelationship(id){
            post('modify_relationship', {to_id:id, type:document.getElementById('modType').value});
        }
        function removeRelationship(id){
            if(confirm('Remove relationship?'))
                post('remove_relationship', {to_id:id});
        }

        network.on('click', params => {
            if(params.nodes.length){
                const id = params.nodes[0];

                // Don't show a menu for the current user's own node
                if(id === user_id){
                    menu.style.display = 'none';
                    return;
                }

                const {x, y} = params.pointer.DOM;     // vis pointer coords

                // Determine if a relationship already exists between the users
                const hasRel = edges.get().some(e =>
                    (e.from == user_id && e.to == id) ||
                    (e.from == id && e.to == user_id)
                );

                if(hasRel){
                    menu.innerHTML =
                        optionSelect(id,'modType')+
                        '<button onclick="modifyRelationship('+id+')">Modify</button><br>'+
                        '<button onclick="removeRelationship('+id+')">Remove Relationship</button>';
                } else {
                    menu.innerHTML =
                        optionSelect(id,'relType')+
                        '<button onclick="sendRequest('+id+')">Send Request</button>';
                }

                menu.style.left   = x + 'px';
                menu.style.top    = y + 'px';
                menu.style.display = 'block';

                justOpened = true;                     // ← NEW

                // keep vis from selecting the background
                if(params.event?.srcEvent?.preventDefault) params.event.srcEvent.preventDefault();
                if(params.event?.srcEvent?.stopPropagation) params.event.srcEvent.stopPropagation();
            } else {
                menu.style.display = 'none';
            }
        });

        document.addEventListener('click', e => {
            if(justOpened){ justOpened = false; return; } // ← NEW guard
            if(!menu.contains(e.target)) menu.style.display = 'none';
        });
    </script>
</body>
</html>
