// =====================================================================
//  JavaScript for the whole page.
//   1. Finder            filters the team as you type
//   2. Data tools        PHP array -> JSON text -> back to PHP
//   3. Post board        Fetch API  GET (read the posts) and POST (write a post)
//   4. Post viewer       XMLHttpRequest GET (open one post by its number)
//   5. Students          Fetch POST (add a student) and Fetch DELETE (remove one), saved in the XML file
//   6. Side menu         highlights the section you are reading
// =====================================================================
(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }

    // ---------- helpers that show results (they use textContent, so nothing typed can run as code) ----------
    function showResult(box, kind, rows) {            // rows = [ ["Label:", "text"], ... ]
        box.hidden = false;
        box.className = 'result ' + kind;
        box.textContent = '';
        rows.forEach(function (pair) {
            var p = document.createElement('p');
            var label = document.createElement('strong');
            label.textContent = pair[0] + ' ';
            p.appendChild(label);
            p.appendChild(document.createTextNode(pair[1]));
            box.appendChild(p);
        });
    }
    function showError(box, text) { showResult(box, 'err', [['Error:', text]]); }

    function asJson(response) {                       // read JSON even when the server answers with an error code
        return response.json().then(function (data) { return { ok: response.ok, data: data }; });
    }

    // =================================================================
    //  1. FINDER: hide the rows that do not match what was typed
    // =================================================================
    var finderInput = $('finderInput');
    var finderRows  = document.querySelectorAll('.finder-row');

    function filterTeam() {
        var typed = finderInput.value.trim().toLowerCase();
        var shown = 0;
        finderRows.forEach(function (row) {
            var match = row.getAttribute('data-search').indexOf(typed) !== -1;
            row.hidden = !match;
            if (match) shown++;
        });
        $('finderCount').textContent = shown;
        $('finderNone').hidden = shown !== 0;
    }
    finderInput.addEventListener('input', filterTeam);
    document.querySelectorAll('.chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            finderInput.value = chip.getAttribute('data-q');
            filterTeam();
            finderInput.focus();
        });
    });

    // =================================================================
    //  2. DATA TOOLS
    // =================================================================

    // roster status from the members API
    fetch('index.php?api=members')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            var active = data.members.filter(function (m) { return m.status === 'active'; }).length;
            $('rosterNote').textContent = 'The members API reports ' + active + ' of ' + data.count + ' teammates as active.';
        })
        .catch(function () { $('rosterNote').hidden = true; });

    // teammate -> JSON
    $('encodeBtn').addEventListener('click', function () {
        var out = $('decodeOut');
        fetch('index.php?api=encode&member=' + encodeURIComponent($('jsonMember').value))
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP error! Status: ' + response.status);
                return response.json();
            })
            .then(function (data) {
                $('jsonText').value = JSON.stringify(data, null, 2);   // JavaScript object -> JSON text
                out.hidden = true;
            })
            .catch(function (error) { showError(out, error.message); });
    });

    // JSON -> PHP reads it as an object and as an array
    $('decodeBtn').addEventListener('click', function () {
        var out = $('decodeOut');
        fetch('index.php?api=decode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: $('jsonText').value
        })
            .then(asJson)
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    showResult(out, 'ok', [
                        ['Read as an object, name:',  result.data.object_name],
                        ['Read as an array, course:', result.data.array_course],
                        ['Age:',                      result.data.age]
                    ]);
                } else {
                    showError(out, result.data.message || 'The server could not read that JSON.');
                }
            })
            .catch(function (error) { showError(out, error.message); });
    });

    // =================================================================
    //  3. POST BOARD (Fetch API)
    // =================================================================
    var postList   = $('postList');
    var postFilter = $('postFilter');

    // Fetch GET: ask the server for the posts and draw them
    function loadPosts() {
        fetch('index.php?api=posts&to=' + encodeURIComponent(postFilter.value))
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP error! Status: ' + response.status);
                return response.json();
            })
            .then(function (data) {
                postList.textContent = '';
                if (data.posts.length === 0) {
                    var none = document.createElement('p');
                    none.className = 'empty';
                    none.textContent = 'No posts here yet. Yours can be the first.';
                    postList.appendChild(none);
                    return;
                }
                data.posts.forEach(function (post) {
                    var box   = document.createElement('div');
                    box.className = 'msg';

                    var title = document.createElement('b');
                    title.textContent = 'Post #' + post.id + ': ' + post.title;
                    var by    = document.createElement('small');
                    by.textContent = 'by ' + post.author + ' for ' + post.to_name;
                    var text  = document.createElement('p');
                    text.textContent = post.body;

                    var view  = document.createElement('button');   // connects the board to the viewer
                    view.type = 'button';
                    view.className = 'secondary small';
                    view.textContent = 'View in Post viewer';
                    view.addEventListener('click', function () {
                        $('viewId').value = post.id;
                        loadOnePost();
                        $('post-viewer').scrollIntoView();
                    });

                    box.appendChild(title);
                    box.appendChild(document.createTextNode(' '));
                    box.appendChild(by);
                    box.appendChild(text);
                    box.appendChild(view);
                    postList.appendChild(box);
                });
            })
            .catch(function (error) {
                postList.textContent = '';
                var err = document.createElement('p');
                err.className = 'result err';
                err.textContent = 'Could not load the posts. ' + error.message;
                postList.appendChild(err);
            });
    }
    postFilter.addEventListener('change', loadPosts);
    loadPosts();                                      // show the posts as soon as the page opens

    // Fetch POST: send the new post as JSON, then reload the list
    $('postForm').addEventListener('submit', function (event) {
        event.preventDefault();                       // stay on the page, no reload
        var out = $('postOut');

        fetch('index.php?api=posts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                author: $('postAuthor').value,
                to:     parseInt($('postTo').value, 10),
                title:  $('postTitle').value,
                body:   $('postBody').value
            })
        })
            .then(asJson)
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    var post = result.data.post;
                    showResult(out, 'ok', [
                        [result.data.message, ''],
                        ['Post number:', String(post.id)],
                        ['Title:',       post.title],
                        ['For:',         post.to_name]
                    ]);
                    $('postTitle').value = '';
                    $('postBody').value  = '';
                    $('viewId').value    = post.id;   // the viewer is ready to open the new post
                    postFilter.value     = '';        // show everything so the new post is visible
                    loadPosts();
                } else {
                    showError(out, result.data.message || 'The server rejected that post.');
                }
            })
            .catch(function (error) { showError(out, error.message); });
    });

    // =================================================================
    //  4. POST VIEWER (XMLHttpRequest)
    // =================================================================
    function loadOnePost() {
        var out   = $('viewOut');
        var id    = parseInt($('viewId').value, 10);

        if (!(id >= 1)) { showError(out, 'Type a post number, for example 1.'); return; }

        out.hidden = true;

        var xhr = new XMLHttpRequest();                   // 1. make the request object

        xhr.onreadystatechange = function () {            // 2. runs each time the request moves to a new stage
            if (xhr.readyState < 4) return;               // 4 = the request is finished

            if (xhr.status === 0) return;                 // never reached the server; onerror handles that

            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (problem) { /* not JSON */ }

            if (xhr.status === 200 && data && data.post) {          // 200 = OK
                showResult(out, 'ok', [
                    ['Post title:', data.post.title],
                    ['Written by:', data.post.author],
                    ['Written for:', data.post.to_name],
                    ['Time:',       data.post.time],
                    ['Content:',    data.post.body]
                ]);
            } else {
                showError(out, (data && data.message) ? data.message : xhr.status + ' - ' + xhr.statusText);
            }
        };
        xhr.onerror   = function () { showError(out, 'Could not reach the server. Is Apache running?'); };
        xhr.ontimeout = function () { showError(out, 'The server took too long to answer.'); };
        xhr.timeout   = 10000;

        xhr.open('GET', 'index.php?api=post&id=' + id, true);      // 3. true = asynchronous
        xhr.send();                                                // 4. send it
    }
    $('viewBtn').addEventListener('click', loadOnePost);

    // =================================================================
    //  5. STUDENTS: Fetch POST adds a student, Fetch DELETE removes one. The table updates without a reload.
    // =================================================================
    var studentRows = $('studentRows');

    function updateStudentCount() {
        $('studentCount').textContent = studentRows ? studentRows.children.length : 0;
    }

    function addStudentRow(s) {                       // builds one table row, with its Delete button
        var tr = document.createElement('tr');
        [s.id, s.name, s.course, s.year].forEach(function (value) {
            var td = document.createElement('td');
            td.textContent = value;
            tr.appendChild(td);
        });
        var last = document.createElement('td');
        var del  = document.createElement('button');
        del.type = 'button';
        del.className = 'secondary small danger';
        del.textContent = 'Delete';
        del.setAttribute('data-id', parseInt(s.id, 10));
        del.setAttribute('data-name', s.name);
        last.appendChild(del);
        tr.appendChild(last);
        studentRows.appendChild(tr);
    }

    // Fetch POST: send the new student as JSON, then add a row
    $('studentForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var out = $('studentOut');

        fetch('index.php?api=students', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name:   $('sName').value,
                course: $('sCourse').value,
                year:   parseInt($('sYear').value, 10)
            })
        })
            .then(asJson)
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    var s = result.data.student;
                    showResult(out, 'ok', [[result.data.message, ''], ['New student ID:', s.id]]);
                    if (studentRows) { addStudentRow(s); updateStudentCount(); }
                    $('sName').value = '';
                } else {
                    showError(out, result.data.message || 'The server rejected that student.');
                }
            })
            .catch(function (error) { showError(out, error.message); });
    });

    // Fetch DELETE: one listener on the table catches every Delete button, including rows added later
    if (studentRows) {
        studentRows.addEventListener('click', function (event) {
            var btn = event.target.closest('button[data-id]');
            if (!btn) return;

            var out  = $('studentOut');
            var id   = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            if (!window.confirm('Delete ' + name + ' (ID ' + id + ')?')) return;

            fetch('index.php?api=students&id=' + encodeURIComponent(id), { method: 'DELETE' })
                .then(asJson)
                .then(function (result) {
                    if (result.ok && result.data.status === 'success') {
                        btn.closest('tr').remove();
                        updateStudentCount();
                        showResult(out, 'ok', [[result.data.message, ''], ['Removed:', name]]);
                    } else {
                        showError(out, result.data.message || 'The server could not delete that student.');
                    }
                })
                .catch(function (error) { showError(out, error.message); });
        });
    }

    // =================================================================
    //  6. SIDE MENU: highlight the section you are reading
    // =================================================================
    var links = document.querySelectorAll('.topbar nav a');
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (a) {
                    a.classList.toggle('active', a.getAttribute('data-target') === entry.target.id);
                });
            });
        }, { rootMargin: '-20% 0px -65% 0px' });
        links.forEach(function (a) {
            var section = $(a.getAttribute('data-target'));
            if (section) observer.observe(section);
        });
    }
})();
