<?php
/*
Network graph of relationships
1. Shows requests - pending, accepted, rejected
2. Shows search bar
3. Shows context menu when right click - 
  View profile
  Send request
  Change relationship (after request)
  Chat
  Remove friend
4. Relationship streak
*/
	require "config.php";
    /*
    1. user must be logged in
    */
	if(!isset($_SESSION["user_id"]))
	{
		header("Location: index.php");
        exit;
	}
    /*
    2. Fetch data for the page
    */
    $nodes = $pdo->query('SELECT id, username, x_pos, y_pos, avatar FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $edges = $pdo->query('SELECT from_id, to_id, type FROM relationships')->fetchAll(PDO::FETCH_ASSOC);
    $statement = $pdo->prepare(
        'SELECT r.id, r.from_id, r.type, u.username
        FROM requests r
        JOIN users u ON r.from_id = u.id
        WHERE r.to_id = ? AND r.status = "PENDING"');
    $statement->execute([$_SESSION["user_id"]]);
    $requests = $statement->fetchAll(PDO::FETCH_ASSOC);
    /*
    3. Preprocess data format
    */
    $graph_nodes = array_map(function($u){
        $avatar = $u['avatar'];
        return [
            'id'=> (int)$u['id'],
            'label' => $u['username'],
            'x' => (double) $u['x_pos'],
            'y' => (double) $u['y_pos'],
            'shape' => "image",
            'image' => "assets/".$avatar,
            'borderWidth' => 3
        ];
    }, $nodes);
    $graph_edges = array_map(function($e){
        return [
            'from'=> (int)$e['from_id'],
            'to'=> (int)$e['to_id'],
            'label' => $e['type'],
            'arrows' => '',
        ];
    }, $edges);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gossip Chain</title>
    <script src="https://unpkg.com/vis-network@latest/standalone/umd/vis-network.min.js"></script>
    <link  href="https://unpkg.com/vis-network@latest/styles/vis-network.min.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/three@0.152/build/three.min.js"></script>

        <style>
            body
            {
                font-family: system-ui, sans-serif;
                background-color: lightcoral;
                /*background-image: url("assets/map2.jpg");*/
                background-position: center center;
                background-size: cover;
                /*opacity: 0.7;*/
                z-index: -1;

            }
            html, body
            {
                margin: 0;
                padding: 0;
                height: 100%;
                overflow: hidden;
            }
           /* Network graph fills the viewport minus the header and footer */
        #graph          { width:100vw; height:calc(100vh - 80px); border:none; }

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
            padding:0 1rem;
            height:30px;
        }

        /* Scrollable container for chat tabs */
        #chat_tabs      {
            display:flex;
            gap:6px;
            max-width:60%;
            overflow-x:auto;
            padding-right:20px; /* extra space keeps the last tab's × fully visible */
        }

        /* Individual chat tab with a visible close button */
        .chat_tab       { background:#fff; border:1px solid #ccc; border-radius:4px; padding:0 6px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; flex:0 0 auto; }
        .chat_tab button{ margin-left:6px; flex:0 0 auto; background:none; border:none; cursor:pointer; padding:0; line-height:1; font-size:14px; font-weight:bold; color:#333; }
        .context-menu   {
            position:absolute; background:#ffffffee; box-shadow:0 2px 6px rgba(0,0,0,.2);
            border-radius:4px; padding:.5rem; display:none; z-index:10;
        }
        .context-menu button {
            display:block; width:100%; background:none; border:none;
            padding:.25rem 0; cursor:pointer; text-align:left;
        }
        /* Floating chat window fixed to the bottom-right above the bar */
        #chat_bubble   { display:none; border:1px solid #333; background:#fff; padding:.5rem; max-width:300px; position:fixed; bottom:30px; right:0; }

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
            <span>Welcome, <?php echo htmlspecialchars($_SESSION["username"]);?></span>
            <div class="search">
                <input type="text" id="search_input" placeholder="Search user">
                <button id="search_btn">Go</button>
            </div>
        </div>
        <a id="logout_link" href="logout.php">Log Out</a>
    </div>
    <!--
    <div id="search_bar">
        <input type="text" id="search_input" placeholder="Type something...">
        <button id="search_btn" class="btn">Search</button>
        <button id="fit_btn" class="btn">Fit Screen</button>
    </div>
    -->
    <div id="graph">
        
    </div>

    <div id="context_menu" class="context-menu" role="menu" aria-hidden="true">

    </div>
    <div id="bottom_bar">
        <div id="request_area">
            <?php if($requests):foreach($requests as $r):?>
            <div style = "margin-bottom: 4px;">
                <?=htmlspecialchars($r['username'])?> requests <?=htmlspecialchars($r['type'])?>
                <form style="display: inline" action="relationships.php" method="post">
                    <input type="hidden" name="action" value="accept_request">
                    <input type="hidden" name="request_id" value="<?=$r['id']?>">
                    <button type="submit">Accept</button>
                </form>
                <form style="display: inline" action="relationships.php" method="post">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" value="<?=$r['id']?>">
                    <button type="submit">Reject</button> 
                </form>
            </div>
            <?php endforeach;else:?>
            Ready
            <?php endif;?>
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
        //Uses vis network library to render graph
        const GRAPH_NODES = <?php echo json_encode($graph_nodes, JSON_UNESCAPED_UNICODE)?>;
        const GRAPH_EDGES = <?php echo json_encode($graph_edges, JSON_UNESCAPED_UNICODE)?>;
        const CURRENT_USER_ID = <?= (int) $_SESSION["user_id"]?>;

        function debounce(fn, wait = 200)
        {
            let t = null;
            return(...args)=>{
                clearTimeout(t);
                t=setTimeout(()=>fn(...args), wait);
            };
        }
        //Build graph
        const nodes = new vis.DataSet(GRAPH_NODES);
        const edges = new vis.DataSet(GRAPH_EDGES);
        const options = {
            nodes:{
                borderWidth: 3,
                color: {background:'#6B99F7', border:'#3B6DD8', highlight:{background:'#F76B9A', border:'#D43F67'}},
                font: {color:'#000'},
                shapeProperties: {useBorderWithImage: true}
                /*
                shape:'dot',
                size: 18,
                font: {color:'#000'},
                color: {background:'#6B99F7', border:'#3B6DD8', highlight:{background:'#F76B9A', border:'#D43F67'}},
                */

            },
            edges:{
                color: {color:'#888', highlight:'#F76B9A'},
                width: 2
            },
            physics:{
                enabled: false,
                //barnesHut: {springLength: 150}
            },
            interaction:{
                hover:true,
                dragNodes:false,
                dragView: true,
                zoomView: true
            }
        };
        const network = new vis.Network(document.getElementById("graph"), {nodes, edges}, options);

        const map = new Image();
        let map_ready = false;
        map.src = "assets/map.png";
        map.onload = ()=>{
            map_ready = true;
            computeMapScale();
            network.redraw();
        };
        const container = document.getElementById("graph");
        const nodeSpace = (()=>{
            const xs = GRAPH_NODES.map(n=>+n.x || 0);
            const ys = GRAPH_NODES.map(n=>+n.y || 0);
            const minX = Math.min(...xs);
            const maxX = Math.max(...xs);
            const minY = Math.min(...ys);
            const maxY = Math.max(...ys);
            return {
                minX, maxX, minY, maxY,
                width: Math.max(1, maxX-minX),
                height: Math.max(1, maxY-minY),
                cx: (maxX+minX)/2,
                cy: (maxY+minY)/2
            };
        })();
        const worldCenter = (nodeSpace.minX<0 && nodeSpace.maxX>0 && nodeSpace.minY<0 && nodeSpace.maxY>0);
        let mapScale = 1;
        function computeMapScale()
        {
            if(!map_ready)
            {
                return;
            }
            const needW = nodeSpace.width*1.2;
            const needH = nodeSpace.height*1.2;
            const sw = needW/map.width;
            const sh = needH/map.height;
            mapScale = Math.max(sw, sh, 1);
            computeMinZoom();
        }
        let minZoom = 0.02;
        function computeMinZoom()
        {
            const worldW = map.width * mapScale;
            const worldH = map.height * mapScale;
            const cw = container.clientWidth || 1;
            const ch = container.clientHeight || 1;
            const minByW = cw/worldW;
            const minByH = ch/worldH;
            minZoom = Math.max(minByW, minByH)*0.999;
            /*!!!!!*/
        }
        function clampView()
        {
            const scale = network.getScale();
            const pos = network.getViewPosition();
            const worldW = map.width * mapScale;
            const worldH = map.height * mapScale;
            let left, top, right, bottom;
            if(worldCenter)
            {
                left = -worldW/2;
                right = worldW/2;
                top = -worldH/2;
                bottom = worldH/2;
            }
            else
            {
                top = 0;
                left = 0;
                right = worldW;
                bottom = worldH;
            }
            const halfW = container.clientWidth/scale/2;
            const halfH = container.clientHeight/scale/2;
            const minX = left + halfW;
            const maxX = right - halfW;
            const minY = top + halfH;
            const maxY = bottom - halfH;
            const clampX = Math.min(Math.max(minX, pos.x), maxX);
            const clampY = Math.min(Math.max(minY, pos.y), maxY);
            const clampScale = Math.max(scale, minZoom);
            if(clampX !== pos.x || clampY !== pos.y || clampScale !== scale)
            {
                network.moveTo({
                    position: {
                        x: clampX,
                        y: clampY
                    },
                    scale: clampScale
                });
            }

        }
        network.on("beforeDrawing", ctx=>{
            if(!map_ready)
            {
                return;
            }
            const scale = network.getScale();
            const view = network.getViewPosition();
            const w = map.width * mapScale;
            const h = map.height * mapScale;
            let mapX, mapY;
            if(worldCenter)
            {
                mapX = -w/2;
                mapY = -h/2;
            }
            else
            {
                mapX = 0;
                mapY = 0;
            }
            const cx = ctx.canvas.width/2;
            const cy = ctx.canvas.height/2;
            const drawX = cx+(mapX-view.x)*scale;
            const drawY = cy+(mapY-view.y)*scale;
            ctx.save();
            ctx.imageSmoothingEnabled = true;
            ctx.globalAlpha = 1;
            ctx.drawImage(map, drawX, drawY, w*scale, h*scale);
            ctx.restore();
        });

        network.on("zoom", clampView);
        network.on("dragEnd", clampView);
        window.addEventListener("resize", ()=>{computeMinZoom(); clampView();});
        function initialCenter()
        {
            const worldW = map.width * mapScale;
            const worldH = map.height * mapScale;
            let target = {x: nodeSpace.cx, y: nodeSpace.cy};
            if(!worldCenter)
            {
                target = {x: worldW/2, y: worldH/2};
            }
            const wpx = container.clientWidth || 1;
            const hpx = container.clientHeight || 1;
            const byW = wpx/worldW;
            const byH = hpx/worldH;
            const startScale = Math.max(byW, byH) * 0.95;
            network.moveTo({
                position: target,
                scale: Math.max(startScale, minZoom),
                animation: {duration: 0}
            });
        }
        if(map.complete)
        {
            map_ready = true;
            computeMapScale();
            initialCenter();
        }
        else{
            map.onload = ()=>{
                map_ready=true;
                computeMapScale();
                initialCenter();
            };
        }
        if(nodes.get(CURRENT_USER_ID))
        {
            network.focus(CURRENT_USER_ID, {scale:1.6, animation:{duration: 800}});
        }
        else
        {
            network.fit({animation:{duration: 600}});
        }

        //search by username
        function searchUser(text)
        {
            if(!text)
            {
                return;
            }
            const lower = text.toLowerCase();
            const match_id = nodes.getIds().find(id=>nodes.get(id).label.toLowerCase()===lower) || 
            nodes.getIds().find(id=>nodes.get(id).label.toLowerCase().includes(lower));
            if(match_id != null)
            {
                const original = nodes.get(match_id);
                nodes.update({
                    id:match_id,
                    color:{background:'#F76B9A', border:'#D43F67'}
                });
                network.focus(match_id, {scale:3, animation:{duration: 800}});
            }
        }
        document.getElementById("search_btn").addEventListener("click", ()=>{
            searchUser(document.getElementById("search_input").value.trim());
        });
        document.getElementById("search_input").addEventListener("keydown", (e)=>{
            if(e.key === "Enter")
            {
                e.preventDefault();
                searchUser(e.target.value.trim());
            }
        });
        //Context menu
        const context_menu = document.getElementById("context_menu");
        const relationships = ['DATING', 'BEST_FRIEND', 'BROTHER', 'SISTER', 'BEEFING', 'CRUSH'];
        let opened = false;
        function optionSelect(name, exclude)
        {
            const ex = (exclude || "").toUpperCase();
            return '<select id="'+name+'">'+relationships.filter(t=>t!==ex).map(t=>`<option value="${t}">${t}</option>`).join('')+'</select>';
        }
        function post(action, extra)
        {
            const form_data = new URLSearchParams(extra);
            form_data.append('action', action);
            fetch('relationships.php', {
                method:'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: form_data.toString()
            }).then(()=>location.reload());
        }
        function sendRequest(to_id)
        {
            post('request', {to_id: to_id, type: document.getElementById('relationships').value});
        }
        function modifyRelationship(to_id)
        {
            post('modify', {to_id: to_id, type: document.getElementById('relationships2').value});
        }
        function removeRelationship(to_id)
        {
            if(confirm("Are you sure you want to remove the Relationship?"))
            {
                post('remove', {to_id: to_id});
            }
        }






        // ----- Direct messaging helpers -----
        let activeChat = null;                                 // ID of the user currently being chatted with
        let lastMessageId = 0;                                 // Highest message ID seen so far
        const bubble = document.getElementById('chat_bubble'); // Floating chat window element
        const messagesDiv = document.getElementById('chat_messages');
        const tabsDiv = document.getElementById('chat_tabs');  // Container that holds chat tabs
        const tabs = {};                                      // Map of user_id -> tab element

        // Ask server for the most recent message ID to avoid opening old chats on load
        function initLatest(){
            fetch('direct_message.php?action=latest_id')
                .then(r=>console.log(r))
                .then(d=>{ if(d && d.latest) lastMessageId = d.latest; });
        }
        initLatest();

        // Ensure a tab exists for the given user ID and return it
        function ensureTab(id){
            if(tabs[id]) return tabs[id];
            const span = document.createElement('span');
            span.className = 'chat_tab';
            span.textContent = 'Chat with ' + nodes.get(id).label;
            span.addEventListener('click', () => openChat(id));
            const btn = document.createElement('button');
            btn.innerHTML = '&times;'; // × symbol for clear visibility
            btn.addEventListener('click', e => { e.stopPropagation(); closeChat(id); });
            span.appendChild(btn);
            tabsDiv.appendChild(span);
            tabsDiv.scrollLeft = tabsDiv.scrollWidth; // auto-scroll so new tab is fully in view
            tabs[id] = span;
            return span;
        }
        // Display the chat bubble and load history for a given user
        function openChat(id){
            ensureTab(id);
            activeChat = id;
            bubble.style.display = 'block';
            loadMessages();
        }

        // Close the tab and hide the bubble if the active chat is closed
        function closeChat(id){
            if(tabs[id]){ tabs[id].remove(); delete tabs[id]; }
            if(activeChat === id){
                activeChat = null;
                bubble.style.display = 'none';
            }
        }

        // Fetch the full conversation with the active chat partner
        function loadMessages(){
            if(activeChat === null) return;
            fetch('direct_message.php?action=retrieve&to_id='+activeChat)
                .then(r=>r.json())
                .then(msgs=>{
                    messagesDiv.innerHTML = msgs.map(m=>`<div><strong>${m.from_id==CURRENT_USER_ID?'Me':nodes.get(m.from_id).label}:</strong> ${m.message}</div>`).join('');
                    if(msgs.length) lastMessageId = msgs[msgs.length-1].id;
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                });
        }

        // Poll for new messages and open tabs as senders arrive
        function checkIncoming(){
            fetch('direct_message.php?action=latest&since='+lastMessageId)
                .then(r=>r.json())
                .then(arr=>{
                    if(!Array.isArray(arr) || !arr.length) return;
                    let reload = false;
                    arr.forEach(m=>{
                        if(m.id>lastMessageId) lastMessageId = m.id;
                        ensureTab(m.from_id);
                        if(activeChat === m.from_id) reload = true;
                    });
                    if(activeChat===null) openChat(arr[arr.length-1].from_id);
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
            fetch('direct_message.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'send', to_id:activeChat, message:text})
            }).then(()=>{
                document.getElementById('chat_input').value='';
                loadMessages();
            });
        });






        network.on('click', params => {
            if(params.nodes.length)
            {
                const id = params.nodes[0];
                if(id === CURRENT_USER_ID)
                {
                    context_menu.style.display = "none";
                    return;
                }
                const {x,y} = params.pointer.DOM;
                const rel = edges.get().find(e=>
                    (e.from==CURRENT_USER_ID&&e.to==id) || 
                    (e.from==id&&e.to==CURRENT_USER_ID));
                const has_relationship = Boolean(rel);
                if(has_relationship)
                {
                    const current_type = rel.label;
                    context_menu.innerHTML = 
                        '<button onclick="openChat('+id+')">Message</button>'+
                        optionSelect("modType", current_type) + 
                        '<button onclick="modifyRelationship('+id+')">Modify</button><br>'+
                        '<button onclick="removeRelationship('+id+')">Remove Relationship</button>';
                }
                else
                {
                    context_menu.innerHTML = 
                        optionSelect("relationships") + 
                        '<button onclick="sendRequest('+id+')">Send Request</button>';
                }
                context_menu.style.left = x+"px";
                context_menu.style.top = y+"px";
                context_menu.style.display = "block";
                opened = true;
                if(params.event?.srcEvent?.preventDefault)
                {
                    params.event.srcEvent.preventDefault();
                }
                if(params.event?.srcEvent?.stopPropagation)
                {
                    params.event.srcEvent.stopPropagation();
                }
            }
            else
            {
                context_menu.style.display = "none";
            }
        });
        document.addEventListener('click', e => {
            if(opened)
            {
                opened = false;
                return;
            }
            if(!context_menu.contains(e.target))
            {
                context_menu.style.display = "none";
            }
        });
    </script>
</body>
</html>