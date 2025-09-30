<?php


function ig_get_management_styles(){

    return "<style>.ig-manage-container{font-family:sans-serif;max-width:700px;margin:auto}.ig-form-section,.ig-list-section{border:1px solid #ccc;padding:20px;border-radius:8px;margin-bottom:20px}.ig-list{list-style-type:none;padding:0}.ig-list li{display:flex;align-items:center;justify-content:space-between;padding:10px;border-bottom:1px solid #eee}.ig-list .item-name{font-weight:bold}.ig-edit-form{display:none;margin-top:10px}.ig-notice{padding:10px 15px;border-radius:5px;margin-bottom:15px}.ig-notice-success{background-color:#d4edda;color:#155724}.ig-notice-error{background-color:#f8d7da;color:#721c24}</style>";

}


function ig_get_management_script(){
    return "<script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.edit-btn').forEach(t=>{t.addEventListener('click',e=>{const t=e.target.getAttribute('data-id'),o=e.target.getAttribute('data-type');document.getElementById(o+'-details-'+t).style.display='none',document.getElementById('edit-form-'+o+'-'+t).style.display='block'})}),document.querySelectorAll('.cancel-edit-btn').forEach(t=>{t.addEventListener('click',e=>{const t=e.target.getAttribute('data-id'),o=e.target.getAttribute('data-type');document.getElementById(o+'-details-'+t).style.display='block',document.getElementById('edit-form-'+o+'-'+t).style.display='none'})})});</script>";
}
