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
    $nodes = $pdo->query('SELECT id, username, x_pos, y_pos, avatar, signature FROM users')->fetchAll(PDO::FETCH_ASSOC);
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
            'borderWidth' => 3,
            'signature' => $u['signature'] ?? ''
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
        .context-menu {
            position:absolute;
            background:#ffffff;
            box-shadow:0 16px 40px rgba(15,23,42,0.18);
            border-radius:10px;
            padding:0;
            display:none;
            z-index:10;
            min-width:240px;
            border:1px solid rgba(15,23,42,0.08);
            overflow:hidden;
        }
        .context-menu .menu-item {
            padding:0.75rem 1rem;
        }
        .context-menu .menu-item + .menu-item {
            border-top:1px solid rgba(148,163,184,0.25);
        }
        .context-menu .menu-title {
            font-weight:600;
            font-size:0.9rem;
            color:#1f2937;
            margin-bottom:0.4rem;
        }
        .context-menu .menu-button {
            width:100%;
            border:none;
            border-radius:8px;
            padding:0.45rem 0.75rem;
            cursor:pointer;
            font-weight:600;
            background:linear-gradient(135deg, #4f46e5, #6366f1);
            color:#fff;
            transition:transform .12s ease, box-shadow .12s ease;
        }
        .context-menu .menu-button:hover {
            transform:translateY(-1px);
            box-shadow:0 10px 20px rgba(99,102,241,0.3);
        }
        .context-menu .menu-button.secondary {
            background:#f3f4f6;
            color:#1f2937;
        }
        .context-menu .menu-button.secondary:hover {
            box-shadow:0 8px 16px rgba(107,114,128,0.25);
        }
        .context-menu .menu-button.danger {
            background:linear-gradient(135deg, #ef4444, #f87171);
        }
        .context-menu .menu-select,
        .context-menu textarea {
            width:100%;
            border:1px solid rgba(148,163,184,0.5);
            border-radius:8px;
            padding:0.45rem 0.6rem;
            font-family:inherit;
            font-size:0.9rem;
            box-sizing:border-box;
            background:#fff;
        }
        .context-menu textarea {
            resize:vertical;
            min-height:72px;
            line-height:1.4;
        }
        .context-menu .menu-note {
            color:#6b7280;
            font-size:0.8rem;
            margin-bottom:0.35rem;
        }
        .context-menu .signature-text {
            color:#334155;
            font-size:0.9rem;
            line-height:1.45;
            white-space:pre-wrap;
        }
        .context-menu .signature-text.muted {
            color:#9ca3af;
            font-style:italic;
        }
        .context-menu .menu-footer {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:0.75rem;
            flex-wrap:wrap;
        }
        .context-menu .menu-counter {
            font-size:0.75rem;
            color:#6b7280;
        }
        .context-menu .menu-actions {
            display:flex;
            gap:0.5rem;
        }
        .context-menu .menu-feedback {
            margin-top:0.4rem;
            font-size:0.75rem;
            color:#059669;
            opacity:0;
            transition:opacity .2s ease;
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
                enabled: true,
                barnesHut: {springLength: 150}
            },
            interaction:{
                hover:true,
                dragNodes:false,
                dragView: true,
                zoomView: true
            }
        };
        const network = new vis.Network(document.getElementById("graph"), {nodes, edges}, options);


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
        const SIGNATURE_LIMIT = 160;
        let opened = false;

        function escapeHtml(str = '')
        {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatSignatureText(text = '')
        {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }

        function optionSelect(id, exclude, labelText)
        {
            const ex = (exclude || '').toUpperCase();
            const optionsHtml = relationships
                .filter(t => !ex || t !== ex)
                .map(t => `<option value="${t}">${t.replace(/_/g, ' ')}</option>`)
                .join('');
            return `
                <label class="menu-note" for="${id}">${labelText}</label>
                <select id="${id}" class="menu-select">${optionsHtml}</select>
            `;
        }

        function renderSignaturePreview(node)
        {
            const signature = (node.signature || '').trim();
            const header = `${escapeHtml(node.label || 'User')}'s Signature`;
            const content = signature
                ? `<p class="signature-text">${formatSignatureText(signature)}</p>`
                : `<p class="signature-text muted">This friend has not written a signature yet.</p>`;
            return `
                <div class="menu-item signature-block">
                    <div class="menu-title">${header}</div>
                    ${content}
                </div>
            `;
        }

        function showMenuAt(x, y, html)
        {
            context_menu.innerHTML = html;
            context_menu.style.left = x + 'px';
            context_menu.style.top = y + 'px';
            context_menu.style.display = 'block';
            opened = true;
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
            const select = document.getElementById('relationships_select');
            if(!select) return;
            post('request', {to_id: to_id, type: select.value});
        }

        function modifyRelationship(to_id)
        {
            const select = document.getElementById('relationships_select2');
            if(!select) return;
            post('modify', {to_id: to_id, type: select.value});
        }

        function removeRelationship(to_id)
        {
            if(confirm("Are you sure you want to remove the relationship?"))
            {
                post('remove', {to_id: to_id});
            }
        }

        function setSignatureFeedback(text)
        {
            const el = document.getElementById('signature_feedback');
            if(!el) return;
            el.textContent = text;
            el.style.opacity = 1;
            setTimeout(()=>{ el.style.opacity = 0; }, 1600);
        }

        function updateSignatureCounter()
        {
            const textarea = document.getElementById('signature_input');
            const counter = document.getElementById('signature_counter');
            if(!textarea || !counter) return;
            counter.textContent = `${textarea.value.length}/${SIGNATURE_LIMIT}`;
        }

        function submitSignature(value)
        {
            const form_data = new URLSearchParams();
            form_data.append('signature', value);
            return fetch('profile.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: form_data.toString()
            })
            .then(r=>r.json())
            .then(res=>{
                if(!res || !res.success)
                {
                    throw new Error('Failed');
                }
                nodes.update({id: CURRENT_USER_ID, signature: res.signature || ''});
                setSignatureFeedback(res.message || 'Saved');
                return res;
            });
        }

        function saveSignature()
        {
            const textarea = document.getElementById('signature_input');
            if(!textarea) return;
            const text = textarea.value.trim();
            if(text.length > SIGNATURE_LIMIT)
            {
                alert(`Signature is too long. Max ${SIGNATURE_LIMIT} characters.`);
                return;
            }
            submitSignature(text).catch(()=>alert('Unable to update signature right now. Please try again later.'));
        }

        function clearSignature()
        {
            const textarea = document.getElementById('signature_input');
            if(!textarea) return;
            textarea.value = '';
            updateSignatureCounter();
            submitSignature('').catch(()=>alert('Unable to clear signature right now. Please try again later.'));
        }

        function showSelfMenu(position)
        {
            const selfNode = nodes.get(CURRENT_USER_ID) || {signature:''};
            const markup = `
                <div class="menu-item">
                    <div class="menu-title">Share your moment</div>
                    <textarea id="signature_input" maxlength="${SIGNATURE_LIMIT}" placeholder="Write a short signature..."></textarea>
                    <div class="menu-footer">
                        <span class="menu-counter" id="signature_counter">0/${SIGNATURE_LIMIT}</span>
                        <div class="menu-actions">
                            <button class="menu-button secondary" onclick="clearSignature()">Clear</button>
                            <button class="menu-button" onclick="saveSignature()">Save</button>
                        </div>
                    </div>
                    <div class="menu-feedback" id="signature_feedback" aria-live="polite"></div>
                </div>
            `;
            showMenuAt(position.x, position.y, markup);
            requestAnimationFrame(()=>{
                const textarea = document.getElementById('signature_input');
                if(textarea)
                {
                    textarea.value = selfNode.signature || '';
                    updateSignatureCounter();
                    textarea.addEventListener('input', updateSignatureCounter);
                }
            });
        }

        function showUserMenu(id, position)
        {
            const node = nodes.get(id);
            if(!node) return;
            const rel = edges.get().find(e=>
                (e.from==CURRENT_USER_ID&&e.to==id) ||
                (e.from==id&&e.to==CURRENT_USER_ID));
            const signatureBlock = renderSignaturePreview(node);
            let markup = signatureBlock;
            if(rel)
            {
                const currentType = rel.label || '';
                markup += `
                    <div class="menu-item">
                        <button class="menu-button" onclick="openChat(${id})">Message</button>
                    </div>
                    <div class="menu-item">
                        <div class="menu-title">Relationship</div>
                        <div class="menu-note">Current: ${escapeHtml(currentType)}</div>
                        ${optionSelect('relationships_select2', currentType, 'Change relationship to')}
                        <div class="menu-actions">
                            <button class="menu-button secondary" onclick="modifyRelationship(${id})">Update Relationship</button>
                        </div>
                    </div>
                    <div class="menu-item">
                        <button class="menu-button danger" onclick="removeRelationship(${id})">Remove Relationship</button>
                    </div>
                `;
            }
            else
            {
                markup += `
                    <div class="menu-item">
                        ${optionSelect('relationships_select', '', 'Send relationship request as')}
                        <div class="menu-actions">
                            <button class="menu-button" onclick="sendRequest(${id})">Send Request</button>
                        </div>
                    </div>
                `;
            }
            showMenuAt(position.x, position.y, markup);
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
                const position = params.pointer.DOM;
                if(id === CURRENT_USER_ID)
                {
                    showSelfMenu(position);
                }
                else
                {
                    showUserMenu(id, position);
                }
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
                context_menu.style.display = 'none';
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