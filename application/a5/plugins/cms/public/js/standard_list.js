var selected_nodes = {};
var latest_clicked_row = null;

function is_selected_node(id)
{
	if (selected_nodes[id]) { return true; }
	return false;
}

function do_node_selected(id)
{
	var o = document.getElementById('node_' + id);
	if (o) { o.className = 'selected'; return true; }
	return false;
}

function select_node(id)
{
	if (do_node_selected(id)) { selected_nodes[id] = id; }
	check_all_buttons();
}

function unselect_all()
{
	for (i in selected_nodes)
	{ unselect_node(selected_nodes[i]); }
}

function unselect_node(id)
{
	var o = document.getElementById('node_' + id);
	if (o) { o.className = null; }
	delete(selected_nodes[id]);
	check_all_buttons();
}

function clicked_on_node(id, evt)
{
	if (evt && !evt.ctrlKey && !evt.metaKey && !evt.shiftKey) { unselect_all(); }
	if (is_selected_node(id)) { unselect_node(id); } else { select_node(id); }
	
	var tr_obj = document.getElementById('node_' + id);
	var tb_obj = tr_obj.parentNode;
	
	if (evt && evt.shiftKey)
	{
		var prev_idx = window.latest_clicked_row;
		var next_idx = tr_obj.rowIndex;
		
		if (tb_obj && prev_idx >= 0 && next_idx >= 0)
		{
			var start_idx = (prev_idx > next_idx ? next_idx : prev_idx);
			var finish_idx = (prev_idx > next_idx ? prev_idx : next_idx);
			for (var i = start_idx + 1; i < finish_idx; i++)
			{
				if (tb_obj.rows[i].id.indexOf('node_') == 0)
				{
					var node_id = tb_obj.rows[i].id.substr("node_".length);
					if (is_selected_node(node_id)) { unselect_node(node_id); }
					else { select_node(node_id); }
				}
			}
		}
	}
	
	if (tr_obj) { window.latest_clicked_row = tr_obj.rowIndex; }
}

function double_clicked_on_node(id, params) { edit_node(id); }