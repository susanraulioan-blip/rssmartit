<?php
/**
 * Plugin Name: RS Smart IT Business Manager
 * Description: ERP/CRM privat în frontend. Include configurarea companiei, dashboard și modulul Furnizori.
 * Version: 0.2.5
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: RS Smart IT
 * Text Domain: rs-smart-it-business-manager
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

final class RSIT_Business_Manager {
 const VERSION='0.2.5', SLUG='rs-smart-it-business-manager', CAP='rsit_access_business_manager';
 private static ?self $i=null;
 public static function instance():self{return self::$i??=new self();}
 private function __construct(){
  add_action('plugins_loaded',[$this,'upgrade']); add_action('init',[$this,'assets']);
  add_action('wp_enqueue_scripts',[$this,'enqueue']); add_action('template_redirect',[$this,'protect']);
  add_filter('template_include',[$this,'template'],99);
  add_action('admin_post_rsit_supplier_save',[$this,'supplier_save']);
  add_action('admin_post_rsit_supplier_archive',[$this,'supplier_archive']);
  add_action('admin_post_rsit_po_save',[$this,'po_save']);
  add_action('admin_post_rsit_po_status',[$this,'po_status']);
  add_action('admin_post_rsit_receipt_save',[$this,'receipt_save']);
  add_action('admin_post_rsit_receipt_document_upload',[$this,'receipt_document_upload']);
  add_action('admin_post_rsit_receipt_document_archive',[$this,'receipt_document_archive']);
 }
 public static function activate():void{self::schema();self::grant();self::page();flush_rewrite_rules();}
 public static function deactivate():void{flush_rewrite_rules();}
 public function upgrade():void{if(get_option('rsit_bm_db_version')!==self::VERSION)self::schema();}
 private static function t(string $name):string{global $wpdb;return $wpdb->prefix.'rsit_'.$name;}
 private static function schema():void{
  global $wpdb;$cc=$wpdb->get_charset_collate();require_once ABSPATH.'wp-admin/includes/upgrade.php';
  dbDelta("CREATE TABLE ".self::t('companies')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   company_name varchar(190) NOT NULL DEFAULT '',
   tax_id varchar(32) NOT NULL DEFAULT '',
   trade_register varchar(64) NOT NULL DEFAULT '',
   vat_enabled tinyint(1) unsigned NOT NULL DEFAULT 0,
   country varchar(100) NOT NULL DEFAULT '',
   county varchar(100) NOT NULL DEFAULT '',
   city varchar(100) NOT NULL DEFAULT '',
   address varchar(255) NOT NULL DEFAULT '',
   postal_code varchar(20) NOT NULL DEFAULT '',
   phone varchar(40) NOT NULL DEFAULT '',
   email varchar(190) NOT NULL DEFAULT '',
   website varchar(190) NOT NULL DEFAULT '',
   contact_name varchar(190) NOT NULL DEFAULT '',
   contact_role varchar(120) NOT NULL DEFAULT '',
   logo_id bigint(20) unsigned NOT NULL DEFAULT 0,
   default_currency varchar(3) NOT NULL DEFAULT 'RON',
   default_vat decimal(5,2) NOT NULL DEFAULT 19.00,
   payment_term smallint(5) unsigned NOT NULL DEFAULT 30,
   quote_prefix varchar(12) NOT NULL DEFAULT 'OFR',
   invoice_prefix varchar(12) NOT NULL DEFAULT 'FAC',
   contract_prefix varchar(12) NOT NULL DEFAULT 'CTR',
   intervention_prefix varchar(12) NOT NULL DEFAULT 'INT',
   setup_step tinyint(3) unsigned NOT NULL DEFAULT 1,
   setup_complete tinyint(1) unsigned NOT NULL DEFAULT 0,
   created_by bigint(20) unsigned NOT NULL DEFAULT 0,
   created_at datetime NULL,
   updated_at datetime NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY tax_id (tax_id)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('company_banks')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   company_id bigint(20) unsigned NOT NULL,
   bank_name varchar(190) NOT NULL DEFAULT '',
   iban varchar(64) NOT NULL DEFAULT '',
   currency varchar(3) NOT NULL DEFAULT 'RON',
   is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
   created_at datetime NULL,
   updated_at datetime NULL,
   PRIMARY KEY  (id),
   KEY company_id (company_id)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('suppliers')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   name varchar(190) NOT NULL,
   tax_id varchar(32) NOT NULL,
   trade_register varchar(64) NOT NULL DEFAULT '',
   vat_enabled tinyint(1) unsigned NOT NULL DEFAULT 0,
   category varchar(40) NOT NULL DEFAULT 'other',
   status varchar(20) NOT NULL DEFAULT 'active',
   country varchar(100) NOT NULL DEFAULT 'România',
   county varchar(100) NOT NULL DEFAULT '',
   city varchar(100) NOT NULL DEFAULT '',
   address varchar(255) NOT NULL DEFAULT '',
   postal_code varchar(20) NOT NULL DEFAULT '',
   contact_name varchar(190) NOT NULL DEFAULT '',
   contact_role varchar(120) NOT NULL DEFAULT '',
   phone varchar(40) NOT NULL DEFAULT '',
   mobile varchar(40) NOT NULL DEFAULT '',
   email varchar(190) NOT NULL DEFAULT '',
   website varchar(190) NOT NULL DEFAULT '',
   payment_term smallint(5) unsigned NOT NULL DEFAULT 30,
   currency varchar(3) NOT NULL DEFAULT 'RON',
   discount_rate decimal(5,2) NOT NULL DEFAULT 0.00,
   ordering_procedure text NULL,
   working_hours varchar(190) NOT NULL DEFAULT '',
   notes text NULL,
   created_by bigint(20) unsigned NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   archived_at datetime NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY tax_id (tax_id),
   KEY status (status),
   KEY category (category)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('supplier_contacts')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   supplier_id bigint(20) unsigned NOT NULL,
   name varchar(190) NOT NULL,
   role varchar(120) NOT NULL DEFAULT '',
   department varchar(120) NOT NULL DEFAULT '',
   phone varchar(40) NOT NULL DEFAULT '',
   email varchar(190) NOT NULL DEFAULT '',
   is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY supplier_id (supplier_id)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('purchase_orders')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   order_number varchar(40) NOT NULL DEFAULT '',
   supplier_id bigint(20) unsigned NOT NULL,
   order_date date NOT NULL,
   expected_date date NULL,
   status varchar(24) NOT NULL DEFAULT 'draft',
   currency varchar(3) NOT NULL DEFAULT 'RON',
   payment_term smallint(5) unsigned NOT NULL DEFAULT 30,
   subtotal decimal(15,2) NOT NULL DEFAULT 0.00,
   tax_total decimal(15,2) NOT NULL DEFAULT 0.00,
   grand_total decimal(15,2) NOT NULL DEFAULT 0.00,
   notes text NULL,
   created_by bigint(20) unsigned NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY order_number (order_number),
   KEY supplier_id (supplier_id),
   KEY status (status),
   KEY order_date (order_date)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('purchase_order_items')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   order_id bigint(20) unsigned NOT NULL,
   product_name varchar(255) NOT NULL,
   supplier_sku varchar(100) NOT NULL DEFAULT '',
   quantity decimal(15,3) NOT NULL DEFAULT 1.000,
   unit varchar(20) NOT NULL DEFAULT 'buc',
   unit_price decimal(15,4) NOT NULL DEFAULT 0.0000,
   vat_rate decimal(5,2) NOT NULL DEFAULT 19.00,
   line_subtotal decimal(15,2) NOT NULL DEFAULT 0.00,
   line_tax decimal(15,2) NOT NULL DEFAULT 0.00,
   line_total decimal(15,2) NOT NULL DEFAULT 0.00,
   notes varchar(255) NOT NULL DEFAULT '',
   sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
   PRIMARY KEY  (id),
   KEY order_id (order_id)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('goods_receipts')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   receipt_number varchar(40) NOT NULL DEFAULT '',
   order_id bigint(20) unsigned NOT NULL,
   supplier_id bigint(20) unsigned NOT NULL,
   receipt_date date NOT NULL,
   delivery_note varchar(100) NOT NULL DEFAULT '',
   status varchar(20) NOT NULL DEFAULT 'completed',
   notes text NULL,
   created_by bigint(20) unsigned NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY receipt_number (receipt_number),
   KEY order_id (order_id),
   KEY supplier_id (supplier_id),
   KEY receipt_date (receipt_date)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('goods_receipt_items')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   receipt_id bigint(20) unsigned NOT NULL,
   order_item_id bigint(20) unsigned NOT NULL,
   received_qty decimal(15,3) NOT NULL DEFAULT 0.000,
   unit_cost decimal(15,4) NOT NULL DEFAULT 0.0000,
   notes varchar(255) NOT NULL DEFAULT '',
   PRIMARY KEY  (id),
   KEY receipt_id (receipt_id),
   KEY order_item_id (order_item_id)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('inventory_lots')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   product_name varchar(255) NOT NULL,
   supplier_sku varchar(100) NOT NULL DEFAULT '',
   supplier_id bigint(20) unsigned NOT NULL,
   purchase_order_id bigint(20) unsigned NOT NULL,
   purchase_order_item_id bigint(20) unsigned NOT NULL,
   receipt_id bigint(20) unsigned NOT NULL,
   receipt_item_id bigint(20) unsigned NOT NULL,
   quantity_received decimal(15,3) NOT NULL DEFAULT 0.000,
   quantity_available decimal(15,3) NOT NULL DEFAULT 0.000,
   unit varchar(20) NOT NULL DEFAULT 'buc',
   unit_cost decimal(15,4) NOT NULL DEFAULT 0.0000,
   currency varchar(3) NOT NULL DEFAULT 'RON',
   status varchar(20) NOT NULL DEFAULT 'available',
   received_at date NOT NULL,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY supplier_id (supplier_id),
   KEY purchase_order_id (purchase_order_id),
   KEY receipt_id (receipt_id),
   KEY status (status)
  ) $cc;");
  dbDelta("CREATE TABLE ".self::t('receipt_documents')." (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   receipt_id bigint(20) unsigned NOT NULL,
   supplier_id bigint(20) unsigned NOT NULL,
   purchase_order_id bigint(20) unsigned NOT NULL,
   document_type varchar(40) NOT NULL DEFAULT 'other',
   document_number varchar(100) NOT NULL DEFAULT '',
   document_date date NULL,
   title varchar(190) NOT NULL DEFAULT '',
   attachment_id bigint(20) unsigned NOT NULL,
   mime_type varchar(100) NOT NULL DEFAULT '',
   file_size bigint(20) unsigned NOT NULL DEFAULT 0,
   notes text NULL,
   created_by bigint(20) unsigned NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   archived_at datetime NULL,
   PRIMARY KEY  (id),
   KEY receipt_id (receipt_id),
   KEY supplier_id (supplier_id),
   KEY purchase_order_id (purchase_order_id),
   KEY document_type (document_type)
  ) $cc;");
  self::repair_company_configuration();
  update_option('rsit_bm_db_version',self::VERSION,false);
 }
 private static function repair_company_configuration():void{
  global $wpdb;$t=self::t('companies');
  $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t));
  if($exists!==$t)return;
  $row=$wpdb->get_row("SELECT * FROM $t ORDER BY id ASC LIMIT 1");
  if(!$row)return;
  $identity=trim((string)($row->company_name??''))!=='' && trim((string)($row->tax_id??''))!=='' && trim((string)($row->trade_register??''))!=='';
  if(!$identity)return;
  $data=['setup_step'=>5,'setup_complete'=>1];
  if(empty($row->updated_at))$data['updated_at']=current_time('mysql');
  $wpdb->update($t,$data,['id'=>(int)$row->id]);
  update_option('rsit_bm_setup_repaired_022',1,false);
 }
 private static function grant():void{$r=get_role('administrator');if($r)$r->add_cap(self::CAP);}
 private static function page():void{$p=get_page_by_path(self::SLUG,OBJECT,'page');if(!$p){$id=wp_insert_post(['post_title'=>'RS Smart IT Business Manager','post_name'=>self::SLUG,'post_status'=>'publish','post_type'=>'page','post_content'=>''],true);if(!is_wp_error($id))update_option('rsit_bm_page_id',(int)$id,false);}else update_option('rsit_bm_page_id',(int)$p->ID,false);}
 public function assets():void{$u=plugin_dir_url(__FILE__).'assets/';wp_register_style('rsit-bm',$u.'app.css',[],self::VERSION);wp_register_script('rsit-bm',$u.'app.js',[],self::VERSION,true);}
 private function is_app():bool{$id=(int)get_option('rsit_bm_page_id');return $id?is_page($id):is_page(self::SLUG);}
 public function enqueue():void{if($this->is_app()){wp_enqueue_style('rsit-bm');wp_enqueue_script('rsit-bm');}}
 public function protect():void{if(!$this->is_app())return;nocache_headers();if(!is_user_logged_in())auth_redirect();if(!current_user_can(self::CAP))wp_die('Acces restricționat.','Acces restricționat',['response'=>403]);}
 public function template(string $t):string{return $this->is_app()?plugin_dir_path(__FILE__).'app-template.php':$t;}
 private function url(array $a=[]):string{$id=(int)get_option('rsit_bm_page_id');$u=$id?get_permalink($id):home_url('/'.self::SLUG.'/');return add_query_arg($a,$u);}
 private function company_ready():bool{global $wpdb;$t=self::t('companies');$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t));if($exists!==$t){self::schema();$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t));if($exists!==$t)return false;}$row=$wpdb->get_row("SELECT * FROM $t ORDER BY id ASC LIMIT 1");if(!$row)return false;if((int)($row->setup_complete??0)===1)return true;$identity=trim((string)($row->company_name??''))!==''&&trim((string)($row->tax_id??''))!==''&&trim((string)($row->trade_register??''))!=='';if($identity){$wpdb->update($t,['setup_step'=>5,'setup_complete'=>1,'updated_at'=>current_time('mysql')],['id'=>(int)$row->id]);return true;}return false;}
 private function view():string{$v=sanitize_key(wp_unslash($_GET['view']??'dashboard'));return in_array($v,['dashboard','suppliers','supplier','purchase_orders','purchase_order','receipts','receipt','receipt_detail','inventory'],true)?$v:'dashboard';}
 public function render():void{if(!$this->company_ready()){echo '<main class="rsit-empty"><h1>Configurarea companiei nu este finalizată.</h1><p>Instalează mai întâi versiunea cu wizardul complet sau finalizează configurarea existentă.</p></main>';return;}$v=$this->view();$this->shell_start($v);if($v==='suppliers')$this->suppliers_list();elseif($v==='supplier')$this->supplier_form();elseif($v==='purchase_orders')$this->purchase_orders_list();elseif($v==='purchase_order')$this->purchase_order_form();elseif($v==='receipts')$this->receipts_list();elseif($v==='receipt')$this->receipt_form();elseif($v==='receipt_detail')$this->receipt_detail();elseif($v==='inventory')$this->inventory_list();else$this->dashboard();$this->shell_end();}
 private function shell_start(string $v):void{?><main class="erp"><aside class="sidebar"><div class="brand"><b>RS</b><span>BUSINESS<br>MANAGER</span></div><nav><small>PRINCIPAL</small><a class="<?php echo $v==='dashboard'?'active':'';?>" href="<?php echo esc_url($this->url(['view'=>'dashboard']));?>">⌂ Dashboard</a><small>MASTER DATA</small><a class="<?php echo in_array($v,['suppliers','supplier'],true)?'active':'';?>" href="<?php echo esc_url($this->url(['view'=>'suppliers']));?>">◇ Furnizori</a><small>ACHIZIȚII</small><a class="<?php echo in_array($v,['purchase_orders','purchase_order'],true)?'active':'';?>" href="<?php echo esc_url($this->url(['view'=>'purchase_orders']));?>">▤ Comenzi furnizor</a><a class="<?php echo in_array($v,['receipts','receipt','receipt_detail'],true)?'active':'';?>" href="<?php echo esc_url($this->url(['view'=>'receipts']));?>">◫ Recepții</a><a class="<?php echo $v==='inventory'?'active':'';?>" href="<?php echo esc_url($this->url(['view'=>'inventory']));?>">▦ Inventar intern</a><small>SISTEM</small><a class="disabled" href="#">⚙ Setări <i>curând</i></a></nav><div class="user"><?php $u=wp_get_current_user();?><b><?php echo esc_html($u->display_name);?></b><a href="<?php echo esc_url(wp_logout_url($this->url()));?>">Deconectare</a></div></aside><section class="main"><header><button class="menu" type="button" aria-label="Deschide meniul">☰</button><span>RS SMART IT / ERP CORE</span><a href="<?php echo esc_url($this->url(['view'=>'purchase_order']));?>" class="quick">+ Comandă</a></header><div class="content"><?php }
 private function shell_end():void{?></div></section><button class="overlay" type="button" aria-label="Închide meniul"></button></main><?php }
 private function dashboard():void{global $wpdb;$t=self::t('suppliers');$active=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE archived_at IS NULL AND status='active'");$all=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE archived_at IS NULL");?><div class="page-head"><div><small>DASHBOARD</small><h1>Bun venit în Business Manager</h1><p>Fundația aplicației este activă. Modulul Furnizori este disponibil.</p></div></div><div class="kpis"><article><span>Furnizori activi</span><strong><?php echo esc_html((string)$active);?></strong><a href="<?php echo esc_url($this->url(['view'=>'suppliers']));?>">Deschide modulul →</a></article><article><span>Total furnizori</span><strong><?php echo esc_html((string)$all);?></strong></article><article class="locked"><span>Clienți</span><strong>0</strong><small>Modul neimplementat</small></article><article class="locked"><span>Produse</span><strong>0</strong><small>Modul neimplementat</small></article></div><section class="panel onboarding"><h2>Următorul pas recomandat</h2><p>Adaugă primul furnizor pentru a pregăti baza viitoarelor achiziții, recepții și facturi furnizor.</p><a class="btn" href="<?php echo esc_url($this->url(['view'=>'supplier']));?>">Adaugă furnizor</a></section><?php }
 private function suppliers_list():void{global $wpdb;$t=self::t('suppliers');$q=sanitize_text_field(wp_unslash($_GET['q']??''));$status=sanitize_key(wp_unslash($_GET['status']??'active'));$where=['archived_at IS NULL'];$args=[];if(in_array($status,['active','evaluation','suspended'],true)){$where[]='status=%s';$args[]=$status;}if($q!==''){$like='%'.$wpdb->esc_like($q).'%';$where[]='(name LIKE %s OR tax_id LIKE %s OR contact_name LIKE %s OR email LIKE %s)';array_push($args,$like,$like,$like,$like);}$sql="SELECT * FROM $t WHERE ".implode(' AND ',$where).' ORDER BY name ASC';$rows=$args?$wpdb->get_results($wpdb->prepare($sql,$args)):$wpdb->get_results($sql);?><div class="page-head"><div><small>MASTER DATA</small><h1>Furnizori</h1><p>Identitatea și condițiile comerciale ale partenerilor de achiziții.</p></div><a class="btn" href="<?php echo esc_url($this->url(['view'=>'supplier']));?>">+ Adaugă furnizor</a></div><?php $this->notice();?><form class="filters" method="get"><input type="hidden" name="view" value="suppliers"><input name="q" value="<?php echo esc_attr($q);?>" placeholder="Caută după nume, CUI, contact sau email"><select name="status"><option value="all">Toate statusurile</option><?php foreach(['active'=>'Activ','evaluation'=>'În evaluare','suspended'=>'Suspendat'] as $k=>$l):?><option value="<?php echo esc_attr($k);?>" <?php selected($status,$k);?>><?php echo esc_html($l);?></option><?php endforeach;?></select><button class="btn secondary">Filtrează</button></form><div class="table-wrap"><table><thead><tr><th>Furnizor</th><th>Categorie</th><th>Contact</th><th>Condiții</th><th>Status</th><th></th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">Nu există furnizori pentru filtrul selectat.</td></tr><?php endif;foreach($rows as $r):?><tr><td><b><?php echo esc_html($r->name);?></b><small><?php echo esc_html($r->tax_id);?></small></td><td><?php echo esc_html($this->category_label($r->category));?></td><td><?php echo esc_html($r->contact_name?:'—');?><small><?php echo esc_html($r->email);?></small></td><td><?php echo esc_html((string)$r->payment_term);?> zile<small><?php echo esc_html($r->currency);?> · discount <?php echo esc_html(number_format((float)$r->discount_rate,2));?>%</small></td><td><span class="badge <?php echo esc_attr($r->status);?>"><?php echo esc_html($this->status_label($r->status));?></span></td><td class="actions"><a href="<?php echo esc_url($this->url(['view'=>'supplier','id'=>(int)$r->id]));?>">Editează</a><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_supplier_archive"><input type="hidden" name="id" value="<?php echo (int)$r->id;?>"><?php wp_nonce_field('rsit_supplier_archive_'.$r->id,'nonce');?><button class="link danger" data-confirm="Arhivezi furnizorul?">Arhivează</button></form></td></tr><?php endforeach;?></tbody></table></div><?php }
 private function category_label(string $v):string{return ['hardware'=>'Hardware','software'=>'Software','cloud'=>'Cloud','telecom'=>'Telecom','consumables'=>'Consumabile','service'=>'Service','other'=>'Altele'][$v]??'Altele';}
 private function status_label(string $v):string{return ['active'=>'Activ','evaluation'=>'În evaluare','suspended'=>'Suspendat','archived'=>'Arhivat'][$v]??$v;}
 private function supplier_form():void{global $wpdb;$id=absint($_GET['id']??0);$r=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('suppliers').' WHERE id=%d AND archived_at IS NULL',$id)):null;if($id&&!$r)wp_die('Furnizorul nu a fost găsit.',404);?><div class="page-head"><div><small>FURNIZORI / <?php echo $id?'EDITARE':'NOU';?></small><h1><?php echo $id?'Editează furnizorul':'Adaugă furnizor';?></h1><p>Date generale, contact și condiții comerciale.</p></div><a class="btn secondary" href="<?php echo esc_url($this->url(['view'=>'suppliers']));?>">← Înapoi la listă</a></div><?php $this->notice(); if($id)$this->supplier_order_history($id);?><form class="supplier-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_supplier_save"><input type="hidden" name="id" value="<?php echo $id;?>"><?php wp_nonce_field('rsit_supplier_save','nonce');?><?php $this->section_general($r);$this->section_address($r);$this->section_contact($r);$this->section_commercial($r);?><section class="form-card"><h2>Observații</h2><div class="form-grid"><label class="wide">Procedură comandă<textarea name="ordering_procedure"><?php echo esc_textarea($r->ordering_procedure??'');?></textarea></label><label>Program de lucru<input name="working_hours" value="<?php echo esc_attr($r->working_hours??'');?>"></label><label class="wide">Note interne<textarea name="notes"><?php echo esc_textarea($r->notes??'');?></textarea></label></div></section><div class="savebar"><a href="<?php echo esc_url($this->url(['view'=>'suppliers']));?>">Renunță</a><button class="btn">Salvează furnizorul</button></div></form><?php }
 private function input(string $n,string $l,?object $r,bool $req=false,string $type='text'):void{?><label><?php echo esc_html($l);?><?php if($req):?> <em>*</em><?php endif;?><input type="<?php echo esc_attr($type);?>" name="<?php echo esc_attr($n);?>" value="<?php echo esc_attr((string)($r->$n??''));?>" <?php echo $req?'required':'';?>></label><?php }
 private function section_general(?object $r):void{?><section class="form-card"><h2>Date companie</h2><div class="form-grid"><label class="wide">Denumire furnizor <em>*</em><input name="name" value="<?php echo esc_attr($r->name??'');?>" required></label><?php $this->input('tax_id','CUI / CIF',$r,true);$this->input('trade_register','Registrul Comerțului',$r);?><label>Plătitor TVA<select name="vat_enabled"><option value="1" <?php selected((int)($r->vat_enabled??1),1);?>>Da</option><option value="0" <?php selected((int)($r->vat_enabled??1),0);?>>Nu</option></select></label><label>Categorie<select name="category"><?php foreach(['hardware','software','cloud','telecom','consumables','service','other'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($r->category??'other',$v);?>><?php echo esc_html($this->category_label($v));?></option><?php endforeach;?></select></label></div></section><?php }
 private function section_address(?object $r):void{?><section class="form-card"><h2>Adresă</h2><div class="form-grid"><?php $this->input('country','Țară',$r);$this->input('county','Județ',$r);$this->input('city','Oraș',$r);$this->input('postal_code','Cod poștal',$r);?><label class="wide">Adresă completă<input name="address" value="<?php echo esc_attr($r->address??'');?>"></label></div></section><?php }
 private function section_contact(?object $r):void{?><section class="form-card"><h2>Contact principal</h2><div class="form-grid"><?php $this->input('contact_name','Persoană de contact',$r);$this->input('contact_role','Funcție',$r);$this->input('phone','Telefon',$r);$this->input('mobile','Mobil',$r);$this->input('email','Email',$r,false,'email');$this->input('website','Website',$r,false,'url');?></div></section><?php }
 private function section_commercial(?object $r):void{?><section class="form-card"><h2>Condiții comerciale</h2><div class="form-grid"><label>Termen plată (zile)<input type="number" min="0" max="365" name="payment_term" value="<?php echo esc_attr((string)($r->payment_term??30));?>"></label><label>Monedă<select name="currency"><?php foreach(['RON','EUR','USD'] as $v):?><option <?php selected($r->currency??'RON',$v);?>><?php echo $v;?></option><?php endforeach;?></select></label><label>Discount negociat (%)<input type="number" min="0" max="100" step="0.01" name="discount_rate" value="<?php echo esc_attr((string)($r->discount_rate??0));?>"></label><label>Status<select name="status"><?php foreach(['active','evaluation','suspended'] as $v):?><option value="<?php echo $v;?>" <?php selected($r->status??'active',$v);?>><?php echo esc_html($this->status_label($v));?></option><?php endforeach;?></select></label></div></section><?php }
 private function notice():void{$n=sanitize_key(wp_unslash($_GET['notice']??''));if($n==='saved')echo '<div class="notice">Furnizorul a fost salvat.</div>';elseif($n==='archived')echo '<div class="notice">Furnizorul a fost arhivat.</div>';elseif($n==='duplicate')echo '<div class="notice error">Există deja un furnizor cu acest CUI.</div>';elseif($n==='po_saved')echo '<div class="notice">Comanda furnizor a fost salvată.</div>';elseif($n==='receipt_saved')echo '<div class="notice">Recepția a fost salvată și stocul intern a fost actualizat.</div>';elseif($n==='document_uploaded')echo '<div class="notice">Documentul a fost încărcat și legat de recepție.</div>';elseif($n==='document_archived')echo '<div class="notice">Documentul a fost arhivat.</div>';elseif($n==='upload_error')echo '<div class="notice error">Documentul nu a putut fi încărcat. Verifică tipul și dimensiunea fișierului.</div>';}

 private function po_status_label(string $v):string{return ['draft'=>'Draft','sent'=>'Trimisă','confirmed'=>'Confirmată','partial'=>'Recepționată parțial','received'=>'Recepționată complet','invoiced'=>'Facturată','closed'=>'Închisă','cancelled'=>'Anulată'][$v]??$v;}
 private function supplier_order_history(int $supplier_id):void{global $wpdb;$orders=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('purchase_orders').' WHERE supplier_id=%d ORDER BY order_date DESC,id DESC LIMIT 20',$supplier_id));$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::t('purchase_orders').' WHERE supplier_id=%d',$supplier_id));$value=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(grand_total),0) FROM ".self::t('purchase_orders')." WHERE supplier_id=%d AND status<>'cancelled'",$supplier_id));?><section class="form-card supplier-history"><div class="history-head"><div><h2>Istoric comenzi</h2><p><?php echo esc_html((string)$count);?> comenzi · valoare cumulată <?php echo esc_html(number_format($value,2,',','.'));?></p></div><a class="btn secondary" href="<?php echo esc_url($this->url(['view'=>'purchase_order','supplier_id'=>$supplier_id]));?>">+ Comandă nouă</a></div><?php if(!$orders):?><p class="empty-inline">Nu există comenzi pentru acest furnizor.</p><?php else:?><div class="history-list"><?php foreach($orders as $o):?><a href="<?php echo esc_url($this->url(['view'=>'purchase_order','id'=>(int)$o->id]));?>"><b><?php echo esc_html($o->order_number);?></b><span><?php echo esc_html($o->order_date);?> · <?php echo esc_html($this->po_status_label($o->status));?></span><strong><?php echo esc_html(number_format((float)$o->grand_total,2,',','.').' '.$o->currency);?></strong></a><?php endforeach;?></div><?php endif;?></section><?php }
 private function purchase_orders_list():void{global $wpdb;$q=sanitize_text_field(wp_unslash($_GET['q']??''));$status=sanitize_key(wp_unslash($_GET['status']??'all'));$where=['1=1'];$args=[];if(in_array($status,['draft','sent','confirmed','partial','received','invoiced','closed','cancelled'],true)){$where[]='po.status=%s';$args[]=$status;}if($q!==''){$like='%'.$wpdb->esc_like($q).'%';$where[]='(po.order_number LIKE %s OR s.name LIKE %s)';$args[]=$like;$args[]=$like;}$sql='SELECT po.*,s.name supplier_name FROM '.self::t('purchase_orders').' po JOIN '.self::t('suppliers').' s ON s.id=po.supplier_id WHERE '.implode(' AND ',$where).' ORDER BY po.order_date DESC,po.id DESC';$rows=$args?$wpdb->get_results($wpdb->prepare($sql,$args)):$wpdb->get_results($sql);?><div class="page-head"><div><small>ACHIZIȚII</small><h1>Comenzi furnizor</h1><p>Comenzi, valori și istoric per furnizor.</p></div><a class="btn" href="<?php echo esc_url($this->url(['view'=>'purchase_order']));?>">+ Comandă nouă</a></div><?php $this->notice();?><form class="filters" method="get"><input type="hidden" name="view" value="purchase_orders"><input name="q" value="<?php echo esc_attr($q);?>" placeholder="Număr comandă sau furnizor"><select name="status"><option value="all">Toate statusurile</option><?php foreach(['draft','sent','confirmed','partial','received','invoiced','closed','cancelled'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($status,$v);?>><?php echo esc_html($this->po_status_label($v));?></option><?php endforeach;?></select><button class="btn secondary">Filtrează</button></form><div class="table-wrap"><table><thead><tr><th>Comandă</th><th>Furnizor</th><th>Dată</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">Nu există comenzi.</td></tr><?php endif;foreach($rows as $o):?><tr><td><b><?php echo esc_html($o->order_number);?></b></td><td><?php echo esc_html($o->supplier_name);?></td><td><?php echo esc_html($o->order_date);?></td><td><span class="badge <?php echo esc_attr($o->status);?>"><?php echo esc_html($this->po_status_label($o->status));?></span></td><td><b><?php echo esc_html(number_format((float)$o->grand_total,2,',','.').' '.$o->currency);?></b></td><td class="actions"><a href="<?php echo esc_url($this->url(['view'=>'purchase_order','id'=>(int)$o->id]));?>">Deschide</a></td></tr><?php endforeach;?></tbody></table></div><?php }
 private function purchase_order_form():void{global $wpdb;$id=absint($_GET['id']??0);$po=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('purchase_orders').' WHERE id=%d',$id)):null;if($id&&!$po)wp_die('Comanda nu a fost găsită.',404);$items=$id?$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('purchase_order_items').' WHERE order_id=%d ORDER BY sort_order,id',$id)):[];$suppliers=$wpdb->get_results("SELECT id,name,currency,payment_term FROM ".self::t('suppliers')." WHERE archived_at IS NULL AND status<>'suspended' ORDER BY name");$pref=absint($_GET['supplier_id']??0);?><div class="page-head"><div><small>ACHIZIȚII / <?php echo $id?'EDITARE':'NOUĂ';?></small><h1><?php echo $id?esc_html($po->order_number):'Comandă furnizor nouă';?></h1><p>Produsele introduse vor alimenta istoricul de achiziții. Catalogul va fi creat la recepție.</p></div><div class="head-actions"><?php if($id && !in_array($po->status,['received','cancelled','closed'],true)):?><a class="btn" href="<?php echo esc_url($this->url(['view'=>'receipt','order_id'=>$id]));?>">Recepționează marfă</a><?php endif;?><a class="btn secondary" href="<?php echo esc_url($this->url(['view'=>'purchase_orders']));?>">← Înapoi</a></div></div><?php $this->notice();?><form class="supplier-form po-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_po_save"><input type="hidden" name="id" value="<?php echo $id;?>"><?php wp_nonce_field('rsit_po_save','nonce');?><section class="form-card"><h2>Date comandă</h2><div class="form-grid"><label>Furnizor <em>*</em><select name="supplier_id" id="po-supplier" required><option value="">Selectează furnizorul</option><?php foreach($suppliers as $sp):?><option value="<?php echo (int)$sp->id;?>" data-currency="<?php echo esc_attr($sp->currency);?>" data-term="<?php echo (int)$sp->payment_term;?>" <?php selected((int)($po->supplier_id??$pref),(int)$sp->id);?>><?php echo esc_html($sp->name);?></option><?php endforeach;?></select></label><label>Data comenzii <em>*</em><input type="date" name="order_date" value="<?php echo esc_attr($po->order_date??current_time('Y-m-d'));?>" required></label><label>Data estimată livrare<input type="date" name="expected_date" value="<?php echo esc_attr($po->expected_date??'');?>"></label><label>Status<select name="status"><?php foreach(['draft','sent','confirmed','cancelled'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($po->status??'draft',$v);?>><?php echo esc_html($this->po_status_label($v));?></option><?php endforeach;?></select></label><label>Monedă<select name="currency" id="po-currency"><?php foreach(['RON','EUR','USD'] as $v):?><option <?php selected($po->currency??'RON',$v);?>><?php echo $v;?></option><?php endforeach;?></select></label><label>Termen plată (zile)<input type="number" min="0" max="365" id="po-term" name="payment_term" value="<?php echo esc_attr((string)($po->payment_term??30));?>"></label><label class="wide">Observații<textarea name="notes"><?php echo esc_textarea($po->notes??'');?></textarea></label></div></section><section class="form-card"><div class="history-head"><div><h2>Poziții comandă</h2><p>Denumire liberă, fără import de catalog.</p></div><button type="button" class="btn secondary" id="po-add-item">+ Adaugă poziție</button></div><div class="po-items" id="po-items"><?php if(!$items)$items=[(object)['product_name'=>'','supplier_sku'=>'','quantity'=>1,'unit'=>'buc','unit_price'=>0,'vat_rate'=>19,'notes'=>'']];foreach($items as $i=>$it)$this->po_item_row($it,$i);?></div><div class="po-totals"><span>Subtotal <b id="po-subtotal">0.00</b></span><span>TVA <b id="po-tax">0.00</b></span><span>Total <b id="po-total">0.00</b></span></div></section><div class="savebar"><a href="<?php echo esc_url($this->url(['view'=>'purchase_orders']));?>">Renunță</a><button class="btn">Salvează comanda</button></div></form><template id="po-item-template"><?php $this->po_item_row((object)['product_name'=>'','supplier_sku'=>'','quantity'=>1,'unit'=>'buc','unit_price'=>0,'vat_rate'=>19,'notes'=>''],'__INDEX__');?></template><?php }
 private function po_item_row(object $it,$i):void{?><div class="po-item"><label class="po-product">Produs / descriere <em>*</em><input name="items[<?php echo esc_attr((string)$i);?>][product_name]" value="<?php echo esc_attr($it->product_name);?>" required></label><label>Cod furnizor<input name="items[<?php echo esc_attr((string)$i);?>][supplier_sku]" value="<?php echo esc_attr($it->supplier_sku);?>"></label><label>Cantitate<input class="po-calc" type="number" min="0.001" step="0.001" name="items[<?php echo esc_attr((string)$i);?>][quantity]" value="<?php echo esc_attr((string)$it->quantity);?>"></label><label>UM<input name="items[<?php echo esc_attr((string)$i);?>][unit]" value="<?php echo esc_attr($it->unit);?>"></label><label>Preț unitar<input class="po-calc" type="number" min="0" step="0.0001" name="items[<?php echo esc_attr((string)$i);?>][unit_price]" value="<?php echo esc_attr((string)$it->unit_price);?>"></label><label>TVA %<input class="po-calc" type="number" min="0" max="100" step="0.01" name="items[<?php echo esc_attr((string)$i);?>][vat_rate]" value="<?php echo esc_attr((string)$it->vat_rate);?>"></label><button type="button" class="po-remove" aria-label="Șterge poziția">×</button></div><?php }
 public function po_save():void{if(!current_user_can(self::CAP))wp_die('Acces interzis.',403);check_admin_referer('rsit_po_save','nonce');global $wpdb;$id=absint($_POST['id']??0);$supplier=absint($_POST['supplier_id']??0);$supplier_exists=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::t('suppliers').' WHERE id=%d AND archived_at IS NULL',$supplier));if(!$supplier_exists)wp_die('Furnizor invalid.',400);$statuses=['draft','sent','confirmed','cancelled'];$status=sanitize_key($_POST['status']??'draft');if(!in_array($status,$statuses,true))$status='draft';$currency=strtoupper(sanitize_text_field(wp_unslash($_POST['currency']??'RON')));if(!in_array($currency,['RON','EUR','USD'],true))$currency='RON';$raw=is_array($_POST['items']??null)?wp_unslash($_POST['items']):[];$items=[];$subtotal=0;$tax=0;foreach($raw as $idx=>$x){$name=sanitize_text_field($x['product_name']??'');if($name==='')continue;$qty=max(.001,(float)($x['quantity']??1));$price=max(0,(float)($x['unit_price']??0));$vat=min(100,max(0,(float)($x['vat_rate']??0)));$line=round($qty*$price,2);$ltax=round($line*$vat/100,2);$subtotal+=$line;$tax+=$ltax;$items[]=['product_name'=>$name,'supplier_sku'=>sanitize_text_field($x['supplier_sku']??''),'quantity'=>$qty,'unit'=>sanitize_text_field($x['unit']??'buc'),'unit_price'=>$price,'vat_rate'=>$vat,'line_subtotal'=>$line,'line_tax'=>$ltax,'line_total'=>$line+$ltax,'notes'=>'','sort_order'=>count($items)];}if(!$items)wp_die('Adaugă cel puțin un produs.',400);$data=['supplier_id'=>$supplier,'order_date'=>sanitize_text_field($_POST['order_date']??current_time('Y-m-d')),'expected_date'=>sanitize_text_field($_POST['expected_date']??'')?:null,'status'=>$status,'currency'=>$currency,'payment_term'=>min(365,absint($_POST['payment_term']??30)),'subtotal'=>round($subtotal,2),'tax_total'=>round($tax,2),'grand_total'=>round($subtotal+$tax,2),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'updated_at'=>current_time('mysql')];$wpdb->query('START TRANSACTION');try{if($id){$wpdb->update(self::t('purchase_orders'),$data,['id'=>$id]);$wpdb->delete(self::t('purchase_order_items'),['order_id'=>$id]);}else{$data['order_number']='PENDING-'.wp_generate_password(8,false,false);$data['created_by']=get_current_user_id();$data['created_at']=current_time('mysql');$wpdb->insert(self::t('purchase_orders'),$data);$id=(int)$wpdb->insert_id;$number='PO-'.current_time('Y').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);$wpdb->update(self::t('purchase_orders'),['order_number'=>$number],['id'=>$id]);}foreach($items as $it){$it['order_id']=$id;$wpdb->insert(self::t('purchase_order_items'),$it);}$wpdb->query('COMMIT');}catch(Throwable $e){$wpdb->query('ROLLBACK');wp_die('Comanda nu a putut fi salvată.',500);}wp_safe_redirect($this->url(['view'=>'purchase_order','id'=>$id,'notice'=>'po_saved']));exit;}
 public function po_status():void{wp_die('Acțiune rezervată pentru etapa Recepții.',400);}

 private function receipt_status_label(string $v):string{return ['partial'=>'Parțială','complete'=>'Completă'][$v]??$v;}
 private function receipts_list():void{global $wpdb;$rows=$wpdb->get_results('SELECT gr.*,po.order_number,s.name supplier_name FROM '.self::t('goods_receipts').' gr JOIN '.self::t('purchase_orders').' po ON po.id=gr.order_id JOIN '.self::t('suppliers').' s ON s.id=gr.supplier_id ORDER BY gr.receipt_date DESC,gr.id DESC');?><div class="page-head"><div><small>ACHIZIȚII</small><h1>Recepții marfă</h1><p>Recepții parțiale și complete, legate de comenzile furnizor.</p></div><a class="btn" href="<?php echo esc_url($this->url(['view'=>'receipt']));?>">+ Recepție nouă</a></div><?php $this->notice();?><div class="table-wrap"><table><thead><tr><th>Recepție</th><th>Comandă</th><th>Furnizor</th><th>Dată</th><th>Aviz</th><th>Status</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">Nu există recepții.</td></tr><?php endif;foreach($rows as $r):?><tr><td><a href="<?php echo esc_url($this->url(['view'=>'receipt_detail','id'=>(int)$r->id]));?>"><b><?php echo esc_html($r->receipt_number);?></b></a></td><td><a href="<?php echo esc_url($this->url(['view'=>'purchase_order','id'=>(int)$r->order_id]));?>"><?php echo esc_html($r->order_number);?></a></td><td><?php echo esc_html($r->supplier_name);?></td><td><?php echo esc_html($r->receipt_date);?></td><td><?php echo esc_html($r->delivery_note?:'—');?></td><td><span class="badge <?php echo esc_attr($r->status);?>"><?php echo esc_html($this->receipt_status_label($r->status));?></span></td></tr><?php endforeach;?></tbody></table></div><?php }
 private function receipt_form():void{global $wpdb;$order_id=absint($_GET['order_id']??0);$orders=$wpdb->get_results("SELECT po.id,po.order_number,po.status,s.name supplier_name FROM ".self::t('purchase_orders')." po JOIN ".self::t('suppliers')." s ON s.id=po.supplier_id WHERE po.status IN ('sent','confirmed','partial','draft') ORDER BY po.order_date DESC,po.id DESC");$po=$order_id?$wpdb->get_row($wpdb->prepare('SELECT po.*,s.name supplier_name FROM '.self::t('purchase_orders').' po JOIN '.self::t('suppliers').' s ON s.id=po.supplier_id WHERE po.id=%d',$order_id)):null;$items=[];if($po){$items=$wpdb->get_results($wpdb->prepare('SELECT poi.*,COALESCE((SELECT SUM(gri.received_qty) FROM '.self::t('goods_receipt_items').' gri WHERE gri.order_item_id=poi.id),0) received_before FROM '.self::t('purchase_order_items').' poi WHERE poi.order_id=%d ORDER BY poi.sort_order,poi.id',$order_id));}?><div class="page-head"><div><small>ACHIZIȚII / RECEPȚIE</small><h1>Recepție marfă</h1><p>Marfa recepționată intră în Inventar Intern. Alocarea către client va fi o operațiune separată.</p></div><a class="btn secondary" href="<?php echo esc_url($this->url(['view'=>'receipts']));?>">← Înapoi</a></div><?php if(!$order_id):?><section class="form-card"><h2>Selectează comanda</h2><div class="form-grid"><label class="wide">Comandă furnizor<select onchange="if(this.value)location.href=this.value"><option value="">Selectează...</option><?php foreach($orders as $o):?><option value="<?php echo esc_url($this->url(['view'=>'receipt','order_id'=>(int)$o->id]));?>"><?php echo esc_html($o->order_number.' · '.$o->supplier_name.' · '.$this->po_status_label($o->status));?></option><?php endforeach;?></select></label></div></section><?php return;endif;if(!$po){echo '<div class="notice error">Comanda nu a fost găsită.</div>';return;}?><form class="supplier-form receipt-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_receipt_save"><input type="hidden" name="order_id" value="<?php echo (int)$po->id;?>"><?php wp_nonce_field('rsit_receipt_save_'.$po->id,'nonce');?><section class="form-card"><h2><?php echo esc_html($po->order_number.' · '.$po->supplier_name);?></h2><div class="form-grid"><label>Data recepției <em>*</em><input type="date" name="receipt_date" value="<?php echo esc_attr(current_time('Y-m-d'));?>" required></label><label>Aviz / document livrare<input name="delivery_note"></label><label class="wide">Observații<textarea name="notes"></textarea></label></div></section><section class="form-card"><h2>Cantități recepționate</h2><div class="receipt-items"><?php $open=0;foreach($items as $it):$remaining=max(0,(float)$it->quantity-(float)$it->received_before);if($remaining<=0)continue;$open++;?><div class="receipt-item"><div><b><?php echo esc_html($it->product_name);?></b><small>Cod: <?php echo esc_html($it->supplier_sku?:'—');?> · UM: <?php echo esc_html($it->unit);?></small></div><span>Comandat <b><?php echo esc_html(number_format((float)$it->quantity,3,',','.'));?></b></span><span>Anterior <b><?php echo esc_html(number_format((float)$it->received_before,3,',','.'));?></b></span><span>Rămas <b><?php echo esc_html(number_format($remaining,3,',','.'));?></b></span><label>Recepționat acum<input class="receipt-qty" type="number" min="0" max="<?php echo esc_attr((string)$remaining);?>" step="0.001" name="received[<?php echo (int)$it->id;?>]" value="<?php echo esc_attr((string)$remaining);?>"></label></div><?php endforeach;if(!$open):?><p class="empty-inline">Toate pozițiile acestei comenzi sunt deja recepționate.</p><?php endif;?></div></section><?php if($open):?><div class="savebar"><a href="<?php echo esc_url($this->url(['view'=>'purchase_order','id'=>(int)$po->id]));?>">Renunță</a><button class="btn">Confirmă recepția</button></div><?php endif;?></form><?php }
 private function inventory_list():void{global $wpdb;$rows=$wpdb->get_results("SELECT il.*,s.name supplier_name,po.order_number,gr.receipt_number FROM ".self::t('inventory_lots')." il JOIN ".self::t('suppliers')." s ON s.id=il.supplier_id JOIN ".self::t('purchase_orders')." po ON po.id=il.purchase_order_id JOIN ".self::t('goods_receipts')." gr ON gr.id=il.receipt_id WHERE il.quantity_available>0 ORDER BY il.received_at DESC,il.id DESC");?><div class="page-head"><div><small>INVENTAR</small><h1>Inventar intern</h1><p>Loturi recepționate și disponibile pentru alocarea viitoare către clienți.</p></div></div><div class="table-wrap"><table><thead><tr><th>Produs</th><th>Disponibil</th><th>Cost</th><th>Furnizor</th><th>Sursă</th><th>Data</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">Inventarul intern este gol.</td></tr><?php endif;foreach($rows as $r):?><tr><td><b><?php echo esc_html($r->product_name);?></b><small><?php echo esc_html($r->supplier_sku);?></small></td><td><b><?php echo esc_html(number_format((float)$r->quantity_available,3,',','.').' '.$r->unit);?></b></td><td><?php echo esc_html(number_format((float)$r->unit_cost,4,',','.').' '.$r->currency);?></td><td><?php echo esc_html($r->supplier_name);?></td><td><?php echo esc_html($r->order_number);?><small><?php echo esc_html($r->receipt_number);?></small></td><td><?php echo esc_html($r->received_at);?></td></tr><?php endforeach;?></tbody></table></div><?php }
 public function receipt_save():void{if(!current_user_can(self::CAP))wp_die('Acces interzis.',403);$order_id=absint($_POST['order_id']??0);check_admin_referer('rsit_receipt_save_'.$order_id,'nonce');global $wpdb;$po=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('purchase_orders').' WHERE id=%d',$order_id));if(!$po||$po->status==='cancelled')wp_die('Comandă invalidă.',400);$order_items=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('purchase_order_items').' WHERE order_id=%d',$order_id),OBJECT_K);$raw=is_array($_POST['received']??null)?wp_unslash($_POST['received']):[];$lines=[];foreach($raw as $item_id=>$qty_raw){$item_id=absint($item_id);if(!isset($order_items[$item_id]))continue;$qty=max(0,(float)$qty_raw);if($qty<=0)continue;$before=(float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(received_qty),0) FROM '.self::t('goods_receipt_items').' WHERE order_item_id=%d',$item_id));$remaining=max(0,(float)$order_items[$item_id]->quantity-$before);if($qty>$remaining+0.0001)wp_die('Cantitatea recepționată depășește cantitatea rămasă.',400);$lines[]=['item'=>$order_items[$item_id],'qty'=>$qty];}if(!$lines)wp_die('Introdu cel puțin o cantitate recepționată.',400);$date=sanitize_text_field($_POST['receipt_date']??current_time('Y-m-d'));$wpdb->query('START TRANSACTION');try{$receipt=['receipt_number'=>'PENDING-'.wp_generate_password(8,false,false),'order_id'=>$order_id,'supplier_id'=>(int)$po->supplier_id,'receipt_date'=>$date,'delivery_note'=>sanitize_text_field(wp_unslash($_POST['delivery_note']??'')),'status'=>'partial','notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')];$wpdb->insert(self::t('goods_receipts'),$receipt);$rid=(int)$wpdb->insert_id;if(!$rid)throw new RuntimeException('receipt');$number='REC-'.current_time('Y').'-'.str_pad((string)$rid,5,'0',STR_PAD_LEFT);$wpdb->update(self::t('goods_receipts'),['receipt_number'=>$number],['id'=>$rid]);foreach($lines as $line){$it=$line['item'];$qty=$line['qty'];$wpdb->insert(self::t('goods_receipt_items'),['receipt_id'=>$rid,'order_item_id'=>(int)$it->id,'received_qty'=>$qty,'unit_cost'=>(float)$it->unit_price,'notes'=>'']);$gri=(int)$wpdb->insert_id;if(!$gri)throw new RuntimeException('receipt_item');$wpdb->insert(self::t('inventory_lots'),['product_name'=>$it->product_name,'supplier_sku'=>$it->supplier_sku,'supplier_id'=>(int)$po->supplier_id,'purchase_order_id'=>$order_id,'purchase_order_item_id'=>(int)$it->id,'receipt_id'=>$rid,'receipt_item_id'=>$gri,'quantity_received'=>$qty,'quantity_available'=>$qty,'unit'=>$it->unit,'unit_cost'=>(float)$it->unit_price,'currency'=>$po->currency,'status'=>'available','received_at'=>$date,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);if(!$wpdb->insert_id)throw new RuntimeException('inventory');}$remaining_total=(float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(GREATEST(poi.quantity-COALESCE((SELECT SUM(gri.received_qty) FROM '.self::t('goods_receipt_items').' gri WHERE gri.order_item_id=poi.id),0),0)),0) FROM '.self::t('purchase_order_items').' poi WHERE poi.order_id=%d',$order_id));$new_status=$remaining_total<=0.0001?'received':'partial';$wpdb->update(self::t('purchase_orders'),['status'=>$new_status,'updated_at'=>current_time('mysql')],['id'=>$order_id]);$wpdb->update(self::t('goods_receipts'),['status'=>$new_status==='received'?'complete':'partial'],['id'=>$rid]);$wpdb->query('COMMIT');}catch(Throwable $e){$wpdb->query('ROLLBACK');wp_die('Recepția nu a putut fi salvată.',500);}wp_safe_redirect($this->url(['view'=>'receipts','notice'=>'receipt_saved']));exit;}

 private function document_type_label(string $v):string{return ['supplier_invoice'=>'Factură furnizor','delivery_note'=>'Aviz','warranty'=>'Certificat garanție','transport'=>'Document transport','technical'=>'Fișă tehnică','other'=>'Alt document'][$v]??'Alt document';}
 private function receipt_detail():void{global $wpdb;$id=absint($_GET['id']??0);$r=$wpdb->get_row($wpdb->prepare('SELECT gr.*,po.order_number,s.name supplier_name FROM '.self::t('goods_receipts').' gr JOIN '.self::t('purchase_orders').' po ON po.id=gr.order_id JOIN '.self::t('suppliers').' s ON s.id=gr.supplier_id WHERE gr.id=%d',$id));if(!$r){echo '<div class="notice error">Recepția nu a fost găsită.</div>';return;}$items=$wpdb->get_results($wpdb->prepare('SELECT gri.*,poi.product_name,poi.supplier_sku,poi.unit FROM '.self::t('goods_receipt_items').' gri JOIN '.self::t('purchase_order_items').' poi ON poi.id=gri.order_item_id WHERE gri.receipt_id=%d ORDER BY gri.id',$id));$docs=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('receipt_documents').' WHERE receipt_id=%d AND archived_at IS NULL ORDER BY document_date DESC,id DESC',$id));?><div class="page-head"><div><small>RECEPȚIE / DETALII</small><h1><?php echo esc_html($r->receipt_number);?></h1><p><?php echo esc_html($r->supplier_name.' · '.$r->order_number.' · '.$r->receipt_date);?></p></div><div class="head-actions"><a class="btn secondary" href="<?php echo esc_url($this->url(['view'=>'receipts']));?>">← Recepții</a></div></div><?php $this->notice();?><section class="form-card"><h2>Produse recepționate</h2><div class="history-list receipt-product-list"><?php foreach($items as $it):?><div><b><?php echo esc_html($it->product_name);?></b><span><?php echo esc_html($it->supplier_sku?:'Fără cod furnizor');?></span><strong><?php echo esc_html(number_format((float)$it->received_qty,3,',','.').' '.$it->unit);?></strong></div><?php endforeach;?></div></section><section class="form-card"><div class="history-head"><div><h2>Documente atașate</h2><p>Documentele sunt stocate în Media Library și legate de această recepție.</p></div></div><?php if(!$docs):?><p class="empty-inline">Nu există documente atașate.</p><?php else:?><div class="document-list"><?php foreach($docs as $d):$url=wp_get_attachment_url((int)$d->attachment_id);?><article><div class="doc-icon">DOC</div><div><b><?php echo esc_html($d->title?:$this->document_type_label($d->document_type));?></b><span><?php echo esc_html($this->document_type_label($d->document_type));?><?php if($d->document_number):?> · <?php echo esc_html($d->document_number);?><?php endif;?><?php if($d->document_date):?> · <?php echo esc_html($d->document_date);?><?php endif;?></span><small><?php echo esc_html(size_format((int)$d->file_size));?></small></div><div class="doc-actions"><?php if($url):?><a class="btn secondary" target="_blank" rel="noopener" href="<?php echo esc_url($url);?>">Deschide</a><?php endif;?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_receipt_document_archive"><input type="hidden" name="document_id" value="<?php echo (int)$d->id;?>"><?php wp_nonce_field('rsit_receipt_document_archive_'.$d->id,'nonce');?><button class="link danger" data-confirm="Arhivezi documentul? Fișierul rămâne în Media Library.">Arhivează</button></form></div></article><?php endforeach;?></div><?php endif;?></section><form class="supplier-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rsit_receipt_document_upload"><input type="hidden" name="receipt_id" value="<?php echo (int)$r->id;?>"><?php wp_nonce_field('rsit_receipt_document_upload_'.$r->id,'nonce');?><section class="form-card"><h2>Adaugă document</h2><div class="form-grid"><label>Tip document <em>*</em><select name="document_type" required><?php foreach(['supplier_invoice','delivery_note','warranty','transport','technical','other'] as $v):?><option value="<?php echo esc_attr($v);?>"><?php echo esc_html($this->document_type_label($v));?></option><?php endforeach;?></select></label><label>Titlu<input name="title" placeholder="Ex: Factura NOD octombrie"></label><label>Număr document<input name="document_number"></label><label>Data documentului<input type="date" name="document_date" value="<?php echo esc_attr($r->receipt_date);?>"></label><label class="wide">Fișier <em>*</em><input type="file" name="receipt_document" accept=".pdf,.jpg,.jpeg,.png,.webp" required><small>Formate permise: PDF, JPG, PNG și WebP. Se aplică limita de upload configurată în WordPress.</small></label><label class="wide">Observații<textarea name="notes"></textarea></label></div></section><div class="savebar"><span></span><button class="btn">Încarcă documentul</button></div></form><?php }
 public function receipt_document_upload():void{$receipt_id=absint($_POST['receipt_id']??0);if(!current_user_can(self::CAP)||!current_user_can('upload_files'))wp_die('Nu ai permisiunea de a încărca documente.',403);check_admin_referer('rsit_receipt_document_upload_'.$receipt_id,'nonce');global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('goods_receipts').' WHERE id=%d',$receipt_id));if(!$r)wp_die('Recepție invalidă.',400);if(empty($_FILES['receipt_document']['name']))wp_die('Selectează un fișier.',400);$types=['supplier_invoice','delivery_note','warranty','transport','technical','other'];$type=sanitize_key($_POST['document_type']??'other');if(!in_array($type,$types,true))$type='other';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$allowed=['pdf'=>'application/pdf','jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];$attachment_id=media_handle_upload('receipt_document',0,['post_title'=>sanitize_text_field(wp_unslash($_POST['title']??''))],['test_form'=>false,'mimes'=>$allowed]);if(is_wp_error($attachment_id)){wp_safe_redirect($this->url(['view'=>'receipt_detail','id'=>$receipt_id,'notice'=>'upload_error']));exit;}$file=get_attached_file($attachment_id);$mime=(string)get_post_mime_type($attachment_id);$ok_mimes=array_values($allowed);if(!in_array($mime,$ok_mimes,true)){wp_delete_attachment($attachment_id,true);wp_safe_redirect($this->url(['view'=>'receipt_detail','id'=>$receipt_id,'notice'=>'upload_error']));exit;}$data=['receipt_id'=>$receipt_id,'supplier_id'=>(int)$r->supplier_id,'purchase_order_id'=>(int)$r->order_id,'document_type'=>$type,'document_number'=>sanitize_text_field(wp_unslash($_POST['document_number']??'')),'document_date'=>sanitize_text_field(wp_unslash($_POST['document_date']??''))?:null,'title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'attachment_id'=>(int)$attachment_id,'mime_type'=>$mime,'file_size'=>$file&&file_exists($file)?(int)filesize($file):0,'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')];$wpdb->insert(self::t('receipt_documents'),$data);if(!$wpdb->insert_id){wp_delete_attachment($attachment_id,true);wp_die('Documentul nu a putut fi înregistrat.',500);}wp_safe_redirect($this->url(['view'=>'receipt_detail','id'=>$receipt_id,'notice'=>'document_uploaded']));exit;}
 public function receipt_document_archive():void{$document_id=absint($_POST['document_id']??0);if(!current_user_can(self::CAP))wp_die('Acces interzis.',403);check_admin_referer('rsit_receipt_document_archive_'.$document_id,'nonce');global $wpdb;$doc=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('receipt_documents').' WHERE id=%d',$document_id));if(!$doc)wp_die('Document invalid.',404);$wpdb->update(self::t('receipt_documents'),['archived_at'=>current_time('mysql')],['id'=>$document_id]);wp_safe_redirect($this->url(['view'=>'receipt_detail','id'=>(int)$doc->receipt_id,'notice'=>'document_archived']));exit;}
 public function supplier_save():void{if(!current_user_can(self::CAP))wp_die('Acces interzis.',403);check_admin_referer('rsit_supplier_save','nonce');global $wpdb;$id=absint($_POST['id']??0);$s=fn(string $k)=>sanitize_text_field(wp_unslash($_POST[$k]??''));$name=$s('name');$tax=strtoupper(preg_replace('/\s+/','',$s('tax_id')));if($name===''||$tax==='')wp_die('Denumirea și CUI sunt obligatorii.',400);$dupe=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::t('suppliers').' WHERE tax_id=%s AND id<>%d AND archived_at IS NULL',$tax,$id));if($dupe){wp_safe_redirect($this->url(['view'=>'supplier','id'=>$id,'notice'=>'duplicate']));exit;}$allowed_cat=['hardware','software','cloud','telecom','consumables','service','other'];$allowed_status=['active','evaluation','suspended'];$data=['name'=>$name,'tax_id'=>$tax,'trade_register'=>strtoupper($s('trade_register')),'vat_enabled'=>absint($_POST['vat_enabled']??0),'category'=>in_array($s('category'),$allowed_cat,true)?$s('category'):'other','status'=>in_array($s('status'),$allowed_status,true)?$s('status'):'active','country'=>$s('country'),'county'=>$s('county'),'city'=>$s('city'),'address'=>$s('address'),'postal_code'=>$s('postal_code'),'contact_name'=>$s('contact_name'),'contact_role'=>$s('contact_role'),'phone'=>$s('phone'),'mobile'=>$s('mobile'),'email'=>sanitize_email(wp_unslash($_POST['email']??'')),'website'=>esc_url_raw(wp_unslash($_POST['website']??'')),'payment_term'=>min(365,absint($_POST['payment_term']??30)),'currency'=>in_array(strtoupper($s('currency')),['RON','EUR','USD'],true)?strtoupper($s('currency')):'RON','discount_rate'=>min(100,max(0,(float)($_POST['discount_rate']??0))),'ordering_procedure'=>sanitize_textarea_field(wp_unslash($_POST['ordering_procedure']??'')),'working_hours'=>$s('working_hours'),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'updated_at'=>current_time('mysql')];if($id)$wpdb->update(self::t('suppliers'),$data,['id'=>$id]);else{$data['created_by']=get_current_user_id();$data['created_at']=current_time('mysql');$wpdb->insert(self::t('suppliers'),$data);$id=(int)$wpdb->insert_id;}wp_safe_redirect($this->url(['view'=>'supplier','id'=>$id,'notice'=>'saved']));exit;}
 public function supplier_archive():void{$id=absint($_POST['id']??0);if(!current_user_can(self::CAP))wp_die('Acces interzis.',403);check_admin_referer('rsit_supplier_archive_'.$id,'nonce');global $wpdb;$wpdb->update(self::t('suppliers'),['status'=>'archived','archived_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['id'=>$id]);wp_safe_redirect($this->url(['view'=>'suppliers','notice'=>'archived']));exit;}
}
register_activation_hook(__FILE__,['RSIT_Business_Manager','activate']);register_deactivation_hook(__FILE__,['RSIT_Business_Manager','deactivate']);RSIT_Business_Manager::instance();
