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
        /* Network graph fills the viewport minus the header and footer */
        #graph          { width:100vw; height:calc(100vh - 100px); border:none; }

        /* Polished top bar greeting/search/logout section */
        #top_bar{
            background:#fff;
            border-bottom:1px solid #ccc;
            box-shadow:0 2px 4px rgba(0,0,0,0.05);
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:0.5rem 1rem;
            height:50px;
        }
        #top_bar .left{ display:flex; align-items:center; gap:1rem; }
        #search_input{ padding:.25rem .5rem; border:1px solid #ccc; border-radius:4px; }
        #search_btn{ padding:.25rem .75rem; margin-left:.25rem; }
        #logout_link{ color:#06c; text-decoration:none; font-weight:500; }
        #logout_link:hover{ text-decoration:underline; }

        /* Bottom bar mirrors the top bar styling */
        #bottom_bar{
            background:#fff;
            border-top:1px solid #ccc;
            box-shadow:0 -2px 4px rgba(0,0,0,0.05);
            position:fixed;
            bottom:0;
            width:100%;
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:0.5rem 1rem;
            height:50px;
        }

        /* Scrollable container for chat tabs */
        #chat_tabs      { display:flex; gap:6px; max-width:60%; overflow-x:auto; }

        /* Individual chat tab with close button */
        .chat_tab       { background:#fff; border:1px solid #ccc; border-radius:4px; padding:0 4px; cursor:pointer; white-space:nowrap; display:flex; align-items:center; }
        .chat_tab button{ margin-left:4px; }
        .context-menu   {
            position:absolute; background:#ffffffee; box-shadow:0 2px 6px rgba(0,0,0,.2);
            border-radius:4px; padding:.5rem; display:none; z-index:10;
        }
        .context-menu button {
            display:block; width:100%; background:none; border:none;
            padding:.25rem 0; cursor:pointer; text-align:left;
        }
        /* Floating chat window anchored above the bottom bar */
        #chat_bubble   { display:none; border:1px solid #333; background:#fff; padding:.5rem; max-width:300px; position:fixed; right:0; bottom:0; }

        /* Scrollable area containing message history */
        #chat_messages { height:150px; overflow-y:auto; margin-bottom:.5rem; background:#fefefe; }

        /* Chat input aligned beside Send button */
        #chat_form     { display:flex; gap:4px; }
        #chat_input    { flex:1; }
    </style>
</head>
<body>
    <div id="top_bar">
        <div class="left">
            <span>Hello, <?php echo htmlspecialchars($_SESSION["username"]);?></span>
            <div class="search">
                <input type="text" id="search_input" placeholder="Search user">
                <button id="search_btn">Go</button>
            </div>
        </div>
        <a id="logout_link" href="logout.php">Log Out</a>
    </div>

    <div id="graph"></div>
    <div id="contextMenu" class="context-menu"></div>

    <!-- Fixed footer with relationship requests and chat tabs -->
    <div id="bottom_bar">
        <div id="request_area">
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
        <div id="chat_tabs"></div>
    </div>

    <!-- Floating chat bubble that displays the active conversation -->
    <div id="chat_bubble">
        <div id="chat_messages"></div>
        <form id="chat_form">
            <input type="text" id="chat_input" autocomplete="off">
            <button type="submit">Send</button>
        </form>
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

        // ----- Direct messaging helpers -----
        let activeChat = null;                                 // ID of the user currently being chatted with
        let lastMessageId = 0;                                 // Highest message ID seen so far
        const bubble = document.getElementById('chat_bubble'); // Floating chat window element
        const messagesDiv = document.getElementById('chat_messages');
        const tabsDiv = document.getElementById('chat_tabs');  // Container that holds chat tabs
        const tabs = {};                                      // Map of user_id -> tab element
        const bottomBar = document.getElementById('bottom_bar');

        // Ask server for the most recent message ID to avoid opening old chats on load
        function initLatest(){
            fetch('dm.php?action=latest_id')
                .then(r=>r.json())
                .then(d=>{ if(d && d.latest) lastMessageId = d.latest; });
        }
        initLatest();

        // Keep the chat bubble positioned just above the bottom bar
        function positionBubble(){
            bubble.style.right = '0';
            bubble.style.bottom = bottomBar.offsetHeight + 'px';
        }
        window.addEventListener('resize', positionBubble);

        // Ensure a tab exists for the given user ID and return it
        function ensureTab(id){
            if(tabs[id]) return tabs[id];
            const span = document.createElement('span');
            span.className = 'chat_tab';
            span.textContent = 'Chat with ' + nodes.get(id).label;
            span.addEventListener('click', () => openChat(id));
            const btn = document.createElement('button');
            btn.textContent = 'x';
            btn.addEventListener('click', e => { e.stopPropagation(); closeChat(id); });
            span.appendChild(btn);
            tabsDiv.appendChild(span);
            tabs[id] = span;
            positionBubble();
            return span;
        }

        // Display the chat bubble and load history for a given user
        function openChat(id){
            ensureTab(id);
            activeChat = id;
            bubble.style.display = 'block';
            positionBubble();
            loadMessages();
        }

        // Close the tab and hide the bubble if the active chat is closed
        function closeChat(id){
            if(tabs[id]){ tabs[id].remove(); delete tabs[id]; positionBubble(); }
            if(activeChat === id){
                activeChat = null;
                bubble.style.display = 'none';
            }
        }

        // Fetch the full conversation with the active chat partner
        function loadMessages(){
            if(activeChat === null) return;
            fetch('dm.php?action=fetch&user_id='+activeChat)
                .then(r=>r.json())
                .then(msgs=>{
                    messagesDiv.innerHTML = msgs.map(m=>`<div><strong>${m.sender_id==user_id?'Me':nodes.get(m.sender_id).label}:</strong> ${m.message}</div>`).join('');
                    if(msgs.length) lastMessageId = msgs[msgs.length-1].id;
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                });
        }

        // Poll for new messages and open tabs as senders arrive
        function checkIncoming(){
            fetch('dm.php?action=latest&since='+lastMessageId)
                .then(r=>r.json())
                .then(arr=>{
                    if(!Array.isArray(arr) || !arr.length) return;
                    let reload = false;
                    arr.forEach(m=>{
                        if(m.id>lastMessageId) lastMessageId = m.id;
                        ensureTab(m.sender_id);
                        if(activeChat === m.sender_id) reload = true;
                    });
                    if(activeChat===null) openChat(arr[arr.length-1].sender_id);
                    else if(reload) loadMessages();
                });
        }

        // Regularly refresh the active conversation and check for new messages
        setInterval(loadMessages,3000);
        setInterval(checkIncoming,3000);

        // Send a message when the chat form is submitted
        document.getElementById('chat_form').addEventListener('submit', e=>{
            e.preventDefault();
            if(activeChat===null) return;
            const text = document.getElementById('chat_input').value.trim();
            if(!text) return;
            fetch('dm.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'send', user_id:activeChat, message:text})
            }).then(()=>{
                document.getElementById('chat_input').value='';
                loadMessages();
            });
        });

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
                        '<button onclick="openChat('+id+')">Message</button><br>'+
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
