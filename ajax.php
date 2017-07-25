<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax extends CI_Controller {
	
	public function index()
	{				  
	}	
	
	public function clientdetail()
	{
		/*$rs = squery('select c.*,s.lookupvalue as clientstatus, (SELECT finentry FROM fintx ftx WHERE c.clientid=ftx.clientid AND fintxtypeid=19 ORDER BY fintxid DESC LIMIT 1) AS closingbalance 
					  from client c 
					  left join lookup s on s.lookupid = c.clientstatusid 
					  where c.clientid = %i',array(sget('clientid')));*/
		$rs = $this->client_model->get(sget('clientid'));
		
		if($rs){
			echo json_encode($rs);
		}else{
			echo '';
		}
	}
	
	public function history()
	{
		$historyid = sget('historyid');
		$updatetemplate = sget('updatetemplate');
		//$historyid = 580484;
		//$updatetemplate = 1;
		
		$rs = squery('select h.*,c.firstname,c.clientid,c.lastname,c.clientstatusid,wa.workflowactiontypeid,c.finacccode from history h 
					left join client c on c.clientid = h.clientid 
					left join workflowaction wa on wa.workflowactionid = h.workflowactionid
					where h.historyid = %i',array($historyid));
		$rsarr = $rs->row_array();
		$clientid = $rsarr['clientid'];
		
		//echo '<pre>'; print_r($rs->row_array()); echo '</pre>'; exit;
		
		// update history contents
		if($updatetemplate && $rs->row(0)->workflowactionid){
			$is_bf_client = $rs->row(0)->clientbf; //is_bf_client($rs->row(0)->clientid);
			//var_dump($is_bf_client);
			
			$workfowaction = squery('select * from workflowaction where workflowactionid = %i',array($rs->row(0)->workflowactionid));
			//echo '<pre>'; print_r($workfowaction->row_array()); echo '</pre>'; exit;
			
			if($is_bf_client){
				if(langpref($clientid) == 22){
					$contents = $workfowaction->row(0)->bftemplateeng;
				}else{
					$contents = $workfowaction->row(0)->bftemplateafr;
				}
			}else{
				if(langpref($clientid) == 22){
					$contents = $workfowaction->row(0)->templateeng;
				}else{
					$contents = $workfowaction->row(0)->templateafr;
				}
			}
			//echo langpref($clientid);exit;
			//echo $contents; exit;
		}
		
		// check
		if($rsarr['templateid']){
			// get
			$templaters = $this->template_model->get($rsarr['templateid']);
			
			$content = $templaters['content'];
		}
		
		if($rs->num_rows() == 0){
			echo '';
		}else{	
			$obj = new stdClass();
			
			$contents = (isset($contents)) ? $contents : $rs->row(0)->contents;	   
			$contents = fill_template_contents($clientid, $contents);		  
			//echo $contents; 
			
			// sms
			if($rs->row(0)->workflowactiontypeid == 7){														  
				$contents = str_replace('&nbsp;',' ',strip_tags($contents)); 
			}else{
				$contents = $contents;
			}
			//$contents = mb_convert_encoding($contents,'UTF-8','UTF-8');
			$contents = mb_check_encoding($contents, 'UTF-8') ? $contents : utf8_encode($contents);
			
			//echo $contents; exit;
			
			$obj->contents = $contents;		
			$obj->success = true;
			$obj->historyid = $rs->row(0)->historyid;
			$obj->workflowactiontypeid = $rs->row(0)->workflowactiontypeid;
			
			echo json_encode($obj);
			//echo json_encode($rs->row(0));
		}
	} 
	
	public function skipnotice()
	{
		// set
		$idarr = array(sget('historyid'));
		$this->json_skiptodos($idarr, false);
		
		/*squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,17,%i,%i,%s,current_timestamp,%s)',array(
			user()->companyid,
			user()->userid,
			sget('historyid'),
			'Notice Skipped',
			''
		));
		squery('update history set historyactionid = 57 where historyid = %i',array(sget('historyid')));
		
		// revert
		$historyrs = $this->db->select('*')->from('history')->where('historyid', sget('historyid'))->get()->row_array();
		$clientrs = $this->db->select('*')->from('client')->where('clientid', $historyrs['clientid'])->get()->row_array();
		
		$this->db->where('clientid', $clientrs['clientid']);
		$this->db->update('client', array('disabledaysdelay' => 1));
		
		// update
		update_todostats();*/
		
		$obj = new stdClass();
		$obj->success = true;
		echo json_encode($obj);
	}
	
	public function cancelnotice()
	{
		// set
		$idarr = array(sget('historyid'));
		$this->json_canceltodos($idarr, false);
		
		/*squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,17,%i,%i,%s,current_timestamp,%s)',array(
			user()->companyid,
			user()->userid,
			sget('historyid'),
			'Notice Cancelled',
			''
		));
		squery('update history set historyactionid = 29 where historyid = %i',array(sget('historyid')));
		
		// revert
		$historyrs = $this->db->select('*')->from('history')->where('historyid', sget('historyid'))->get()->row_array();
		$clientrs = $this->db->select('*')->from('client')->where('clientid', $historyrs['clientid'])->get()->row_array();
		
		if(trim($clientrs['historyarr'])){
			$arr = json_decode($clientrs['historyarr'], true);
			
			$this->db->where('clientid', $clientrs['clientid']);
			$this->db->update('client', $arr);
		}
		
		// update
		update_todostats();*/
		
		$retarr['success'] = true;
		echo json_encode($retarr);
	}  
	
	public function sendnotice()
	{
		// set
		$retarr = array();
		$senddate = sget('date');
		$sendtime = sget('time');
		$progressmsg = sget('progressmsg');
		$senddate = ($senddate && $sendtime) ? date("Y-m-d H:i:s", strtotime($senddate.' '.$sendtime)) : null;
		$senddate = null;
		$historyid = sget('historyid');
		
		// post
		/*$sel = $this->input->post('sel');
		$date = $this->input->post('date');
		
		parse_str($sel);*/
		
		// get
		$rs = squery('
				SELECT h.*, c.firstname, c.clientid, c.lastname, c.clientstatusid, c.mobile, c.mobile2, c.email, wa.workflowactiontypeid
					FROM history h 
					LEFT JOIN client c on c.clientid = h.clientid 
					LEFT JOIN workflowaction wa on wa.workflowactionid = h.workflowactionid
					WHERE h.historyid = %i
			', array($historyid));
		$historyrs = $this->history_model->get($historyid);
		$clientrs = $this->client_model->get($historyrs['clientid']);
		
		// progress
		if($progressmsg){
			set_progress($progressmsg);
		}
		
		// sms
		if($rs->row(0)->workflowactiontypeid == 7){
			//$retarr['success'] = true; 
			//$retarr['historyid'] = sget('historyid');
			
			/**/
			if(hascredit(14)){			
				$sms = new Sms();		  
						 
				$contents = '';
				
				if(isset($_POST['contents'])){
					$contents = $_POST['contents'];	
					$contents = charsetfix($contents);
					$contents = str_replace('<p>','',$contents);
					$contents = str_replace('</p>','',$contents);
					$contents = str_replace('&nbsp;','',$contents);
					$contents = str_replace('&nbsp','',$contents);					
					$contents = strip_tags($contents);			
				}				
								
				if(($contents == '') || ($this->uri->segment(3) == 'multiple')){						  
					$contents = charsetfix($rs->row(0)->contents);
					$contents = str_replace('<p>','',$contents);
					$contents = str_replace('</p>','',$contents);
					$contents = str_replace('&nbsp;','',$contents);
					$contents = str_replace('&nbsp','',$contents);
					$contents = strip_tags($contents);
				}
				
				$txid = billcredit(14,$rs->row(0)->clientid);
				
				$smsresult = (!$senddate) ? $sms->SendSMS(array($rs->row(0)->mobile,$rs->row(0)->mobile2), $contents, $txid) : null;
				
				squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,10,%i,%i,%s,current_timestamp,%s)',array(
					user()->companyid,
					user()->userid,
					sget('historyid'),
					'Send SMS',
					$contents
				));
				squery('update history set historyactionid=28, contents=%s where historyid = %i',array($contents,sget('historyid')));
				
				squery('update tx set description=%s, smssenddate=%s where txid = %i', array($contents.' '.$smsresult, $senddate, $txid));			  
				
				$retarr['success'] = true; 
				$retarr['historyid'] = sget('historyid');
				
				// update
				update_todostats();
				updatetoolstats();
			}else{
				$retarr['success'] = false; 
				$retarr['message'] = 'You do not have sufficient credits to send this message.';
			}
			/**/
		}
		
		// print
		if($rs->row(0)->workflowactiontypeid == 8){
			$retarr['success'] = true;
			
			if($this->uri->segment(3) != 'multiple') // do not process documents if it's multiple sends
			{
				$contents = sget('contents',true);
				
				squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,10,%i,%i,%s,current_timestamp,%s)',array(
					user()->companyid,
					user()->userid,
					sget('historyid'),
					'Document Printed',
					$contents
				));
				squery('update history set historyactionid = 28,contents=%s where historyid = %i',array($contents,sget('historyid')));
	 
				/* No cost the document is sent via email or post		  
				squery('insert into tx(companyid,txtypeid,cost,createdon) values(%i,%i,%f,current_timestamp)',array(
					user()->companyid,
					14,
					1
				)); */
				$retarr['historyid'] = sget('historyid');
				
				// save doc
				$filepath = save_historydoc($historyrs, $clientrs);
			}
		}
				
		$retarr['workflowactiontypeid'] = $rs->row(0)->workflowactiontypeid;		
		echo json_encode($retarr);
	}
	
	public function adhocsms()
	{
		// set
		$companyid = (isset(company()->companyid)) ? company()->companyid : null;
		$retarr = array('success' => true);
		$message = sget('smsmessage');
		$fincodearr = $this->input->post('fincodearr');
		$fincodearr = (is_array($fincodearr)) ? array_unique(array_map('trim', $fincodearr)) : null;
		$smsnumber2 = $this->input->post('smsnumber2');
		
		//echo '<pre>'; print_r($fincodearr); echo '</pre>'; exit;
		
		// check
		if(!$companyid){
			echo json_encode(array('success' => false, 'message' => 'Please select a school.'));
			return;
		}
		
		// process
		//$clientrs = (sget('sendtoall')) ? $this->db->select('*')->from('client')->where('companyid', $companyid)->where('deleted IS NULL')->get()->result_array() : array(array('clientid' => sget('smsadhocclientid'), 'mobile' => sget('smsnumber'), 'mobile2' => sget('smsnumber2'))); 
		
		// check
		if(sget('sendtoall')){
			//$clientrs = $this->db->select('*')->from('client')->where('companyid', $companyid)->where('deleted IS NULL')->get()->result_array();

			$clientrs = $this->db->select('aa.date, c.*')->from('client c')
						->join('aafintx aa', 'aa.clientid=c.clientid', 'left')
						->where('aa.current > 0')
						->where('aa.30days <= 500')
						->where('aa.60days is null')
						->where('aa.90days is null')
						->where('aa.120days is null')
						->where('aa.150days is null')
						->where('aa.180days is null')
						->where('DATE(aa.date)>="'.date("2017-06-05").'"')
						->where('c.companyid', $companyid)->get()->result_array();
		}else{
			$clientrs = $this->db->select('*')->from('client')->where('companyid', $companyid)->where('deleted IS NULL')->where('(clientid IN ('.implode(',', $fincodearr).'))')->get()->result_array();
		} 

		// check
		if(count($clientrs) == 0){
			echo json_encode(array('success' => false, 'message' => 'Please select at least one debtor.'));
			return;
		}
		
		// check
		$companyrs = $this->db->select('*')->from('company')->where('companyid', $companyid)->get()->row_array();
		
		// set
		$numcreditsrequired = ceil(count($clientrs)*0.28);
		$companyrs['creditbalance'] = 100000; // override

		// check
		if($numcreditsrequired < $companyrs['creditbalance']){
			$totalrows = count($clientrs);
			
			// loop
			$i = 1;
			foreach($clientrs as $row){
				// update
				set_progress('Sending '.$i.' of '.$totalrows.'...');
				
				//echo '<pre>'; print_r($row); echo '</pre>';
				
				// check
				if(!$row['clientid']){
					//echo 'no client';
					continue;
				}
			
				// fill
				$message = fill_template_contents($row['clientid'], $message);
				
				// set
				$numarr = array_unique(array_filter(array_map('trim', array($row['mobile'], $row['mobile2']))));
				
				// check
				if(count($clientrs) == 1 && $smsnumber2){
					$numarr[] = $smsnumber2;
				}
				
				// loop
				foreach($numarr as $cell){
					$txid = billcredit(14, $row['clientid']);
				
					// send
					$sms = new Sms();													   
					$smsresult = $sms->SendSMS(array($cell), $message, $txid);
					
					// save history
					$insarr = array(
							'historytypeid' => 51, 
							'clientid' => $row['clientid'], 
							'createdon' => date("Y-m-d H:i:s"), 
							'contents' => $message, 
							'historyactionid' => 28
						);
					$this->db->insert('history', $insarr);
					$historyid = $this->db->insert_id();
		
					// save audit
					$insarr = array(
							'companyid' => user()->companyid,
							'audittypeid' => 11,
							'userid' => user()->userid,
							'historyid' => $historyid,
							'entryname' => 'Adhoc SMS',
							'notes' => $message,
							'createdon' => date("Y-m-d H:i:s"),
						);
					$this->db->insert('audit', $insarr);
					$auditid = $this->db->insert_id();
					
					// set
					$smsparts = ceil(strlen($message)/SMS_LENGTH);
					
					// update sms
					$insarr = array(
							'description' => $message.' '.$smsresult,
							'smsparts' => $smsparts
						);
					$this->db->where('txid', $txid);
					$this->db->update('tx', $insarr);
					$txid = $this->db->insert_id();
				}
				
				$i++; // inc
			} // end-foreach
			
			// update
			updatetoolstats();
			
		}else{
			$retarr['success'] = false; 
			$retarr['message'] = 'You do not have sufficient credits to send this message/s.';
		}
		
		
		// return
		echo json_encode($retarr);		
	}
	
	public function logquestion()
	{		
				
		$msg = '<p>School: </p>' . company()->companyname . '</p><p>Query Type: ' . sget('querytype') . '</p>' . sget('question');
		
		/*if(sget('querytype') == 'Software System')
			sendemail('support@jumpingfoxsoftware.com','Question',$msg,null);
		else
			sendemail('daleen@jumpingfoxsoftware.com','Question',$msg,null);*/
		
		sendemail('support@jumpingfoxsoftware.co.za,daleen@jumpingfoxsoftware.com', 'Question', $msg, null);
		
		$obj = new stdClass();
		$obj->success = true;
		echo json_encode($obj);		
	}
	
	public function findsmsnumber()
	{
		$obj = new stdClass();
		
		$search = '%' . sget('finacccode') . '%';
		$rs = squery('select firstname,lastname,mobile,mobile2,clientid from client where (finacccode = %s or firstname like %s or lastname like %s) and companyid = %i',array(sget('finacccode'),$search,$search,user()->companyid));
		
		if($rs->num_rows() > 0)
		{
			if($rs->num_rows() > 1)
			{
				$obj->list = $rs->result_array();
				$obj->success = true;
				$obj->mobile = null;  
				$obj->mobile2 = null;  
				$obj->clientid = null;	 
			}
			else
			{
				$obj->list = null;
				$obj->success = true;
				$obj->mobile = $rs->row(0)->mobile;		
				$obj->mobile2 = $rs->row(0)->mobile2;		
				$obj->clientid = $rs->row(0)->clientid;  
			}
		}		   
		else
			$obj->success = false;

		echo json_encode($obj);		
	} 
	
	public function findemail()
	{
		$retarr = array('success' => false, 'row' => null, 'rs' => null);
		
		$finacccode = trim($this->input->post('finacccode'));
		$rs = $this->db->select('*')->from('client')->where('companyid', company()->companyid)->where('finacccode', $finacccode)->where('deleted IS NULL')->get()->row_array();
		
		// check
		if($rs){
			$retarr['row'] = $rs;
			$retarr['success'] = true;
		}
		
		// check
		if(!$rs){
			$rs = $this->db->select('*')->from('client')
				->where('(finacccode LIKE "%'.$finacccode.'%" OR firstname LIKE "%'.$finacccode.'%" OR lastname LIKE "%'.$finacccode.'%")')
				->where('companyid', company()->companyid)
				->where('deleted IS NULL')
				->limit(50)
				->get()->result_array();
			
			if($rs){
				$retarr['rs'] = $rs;
				$retarr['success'] = true;
			}
		}
		
		echo json_encode($retarr);		
	}

	public function adhocemail()
	{
		$retarr = array('success' => true);
		
		// set
		$companyid = (isset(company()->companyid)) ? company()->companyid : null;
		$clientid = $this->input->post('clientid');
		$email = $this->input->post('email');
		$subject = $this->input->post('subject');
		$message = nl2br($this->input->post('message'));
		$sendtoall = $this->input->post('sendtoall');
		$kbarr = $this->input->post('kbarr');
			$kbarr = (is_array($kbarr)) ? array_unique($kbarr) : array();
		$attachmentarr = $this->input->post('attachmentarr');
			$attachmentarr = (is_array($attachmentarr)) ? array_unique($attachmentarr) : array();
		$emattarr = array();

		// check
		if(!$clientid && !$sendtoall){
			return;
		}
		
		// fill
		if ($clientid) {
			$message = fill_template_contents($clientid, $message);
		}
		
		// process
		$clientrs = ($sendtoall) ? $this->client_model->get(null, array('c.companyid' => $companyid)) : $this->client_model->get(null, array('c.clientid' => $clientid));
		//echo '<pre>'; print_r($clientrs); echo '</pre>'; exit;
		
		// to
		if($sendtoall){
			$email = array();
			
			// loop
			foreach($clientrs as $row){
				$row['email'] = (stristr($row['email'], '@')) ? $row['email'] : '';
				$row['altemail'] = (stristr($row['altemail'], '@')) ? $row['altemail'] : '';
				
				$str = $row['email'].','.$row['altemail'];
				
				$str = str_replace(array(';', ' '), array(',', ''), $str);
				$arr = explode(',', $str);
				
				$email = array_merge($email, $arr);
			}
			
			$email = array_filter(array_map('trim', $email));
		}
		
		// add attachments
		foreach($kbarr as $kbid){
			// get
			$rs = $this->db->select('*')->from('kb')->where('kbid', $kbid)->get()->row_array();
			
			$html = trim($rs['content']);
			
			// check
			if($html){
				$filepath = UPLOADS_DIR.make_uri($rs['title']).'.pdf';
				
				// create
				pdf_create($html, $filepath, false);
				
				$emattarr[] = $filepath;
			}
		}
		foreach($attachmentarr as $attachmentid){
			// get
			$rs = $this->attachment_model->get($attachmentid);
			
			$emattarr[] = $rs['uploadsdir'].$rs['filename'];
		}
		
		// from
		$from = (user()->email) ? user()->email : 'support@jumpingfoxsolutions.com';
		
		// send e-mail
		sendemail($email, $subject, $message, $emattarr, $from);
	
		
		// loop
		foreach($clientrs as $row){
			$clientid = $row['clientid'];
			
			// save history
			$insarr = array(
					'historytypeid' => 70, 
					'clientid' => $clientid, 
					'createdon' => date("Y-m-d H:i:s"), 
					'contents' => $message, 
					'historyactionid' => 28
				);
			$this->db->insert('history', $insarr);
			$historyid = $this->db->insert_id();
			
			// save audit
			squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,11,%i,%i,%s,current_timestamp,%s)',array(
				user()->companyid,
				user()->userid,
				$historyid,
				'Adhoc Email',
				$message
			));
		}
		
				
		echo json_encode($retarr);		
	}
	
	public function clienthistory()
	{
		$obj = new stdClass();
		$obj->html = $this->load->view('admin/common/inc-clienthistory',array('clientid' => sget('clientid')),true); 
		echo json_encode($obj);
	}
	 
	public function addnote()
	{		
		squery('insert into history(historytypeid,clientid,createdon,contents) values(26,%i,current_timestamp,%s)',array(
			sget('clientid'),
			sget('notes')
		));
		
		if(sget('reminder') == '1')
		{
			sendemail(user()->email,'Reminder',sget('notes'),null);
		}
						
		$obj = new stdClass();
		$obj->success = true;
		echo json_encode($obj);		
	}  
	
  
	public function pptemplate()
	{
		$obj = new stdClass();
		$rs = squery('select * from pptemplate where pptemplateid = %i',array(sget('pptemplateid')));
		$obj->success = true;
		$obj->list = $rs->result_array();
		echo json_encode($obj); 
	} 
	
	public function generatedoc()
	{							 
		$obj = new stdClass();
		$rs = squery('select * from client where clientid = %i',array(sget('clientid')));		
		$contents = prepare_contents($rs->row(0),sget('content',true)); 
		$obj->success = true;
		$obj->contents = $contents;
		echo json_encode($obj);		 
	}

	public function confirmhandover($historyid=null)
	{
		// set
		$historyid = (!$historyid && $this->input->get('historyid')) ? $this->input->get('historyid') : $historyid;
		$mdform = $this->input->post('mdform');
		
		// get
		$rs = squery('SELECT h.*, c.firstname, c.clientid, c.finacccode, c.lastname, c.grade, c.class, c.clientstatusid, csl.lookupvalue AS clientstatus, c.finacccode, wa.workflowactionname, c.closingbalance, wa.workflowactiontypeid
					FROM history h 
					LEFT JOIN client c on c.clientid = h.clientid 
					LEFT JOIN workflowaction wa on wa.workflowactionid = h.workflowactionid
					LEFT JOIN lookup csl ON c.clientstatusid=csl.lookupid
					WHERE h.historyactionid != 52 AND (wa.workflowactionid in (19,20)) AND historyactionid NOT IN (29, 57) AND h.historyid = %i',array($historyid))->row_array();
		$ishandover = ($rs && $rs['historyactionid'] != 52 && in_array($rs['workflowactionid'], array(19,20)) && !in_array($rs['historyactionid'], array(29, 57))) ? true : false;
		$send = 0;
		
		//echo $ishandover; exit;
		
		// check
		if($ishandover){
			squery('insert into audit(companyid,audittypeid,userid,historyid, entryname,createdon,notes) values(%n,11,%i,%i,%s,current_timestamp,%s)',array(
				user()->companyid,
				user()->userid,
				$historyid,
				'Handover Confirmed',
				''
			));
			
			squery('update history set historyactionid = 52 where historyid = %i',array($historyid));
			$send = 1;
			
			// get
			$metadatars = $this->metadata_model->get(null, array('md.clientid' => $rs['clientid']));
			$metadataid = ($metadatars) ? $metadatars['metadataid'] : null;
			
			// update
			$mdform['clientid'] = $rs['clientid'];
			$mdform['partyhandedover_familycode'] = $rs['finacccode'];
			
			// save
			$this->metadata_model->save($mdform, $metadataid);
			
			// set
			$inparr = array('clientstatusid' => 'null', 'lastworkflowactionid' => 'null', 'lastworkflowactionon' => 'null', 'handedoveron' => date("Y-m-d H:i:s"));
			
			// save
			$this->client_model->save($inparr, $rs['clientid']);
			
			//echo $rs['clientid'];
		}
		
		$obj = new stdClass();
		$obj->success = true;
		//$obj->historyid = $historyid;
		//$obj->send = $send;
		echo json_encode($obj);
	}  
	
	public function deletehistory($historyid)
	{
		$historyid = decuri($historyid);
		
		// delete
		$this->db->where('historyid', $historyid);
		$this->db->delete('history');
		
		echo 1;
	}
		
	public function updatehistory()
	{
		$historyid = $this->input->post('historyid');
		$contents = $this->input->post('contents');
		
		// save
		$this->db->where('historyid', $historyid);
		$this->db->update('history', array('contents' => $contents));
	}
	
	public function refreshhistory($historyid)
	{
		// set
		$idarr = ($historyid == 'all') ? $this->input->post('sel') : array($historyid);
		$totalrows = count($idarr);
		
		// loop
		foreach($idarr as $i => $historyid){
			$num = ($i+1);
			
			// get
			$sql = '
					h.*, 
					c.firstname, 
					c.clientid, 
					c.lastname, 
					c.clientstatusid, 
					c.finacccode,
					wfa.workflowactiontypeid,
					wfa.bftemplateeng,
					wfa.bftemplateafr,
					wfa.templateeng,
					wfa.templateafr
				';
			$rs = $this->db->select($sql)
					->from('history h')
					->join('client c', 'h.clientid=c.clientid', 'left')
					->join('workflowaction wfa', 'h.workflowactionid=wfa.workflowactionid', 'left')
					->where('h.historyid', $historyid)
					->get()->row_array();
			$clientid = $rs['clientid'];
			
			// set progress
			set_progress('Updating ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			// update history content
			if($rs['clientbf']){
				$contents = (langpref($clientid) == 22) ? $rs['bftemplateeng'] : $rs['bftemplateafr'];
			}else{
				$contents = (langpref($clientid) == 22) ? $rs['templateeng'] : $rs['templateafr'];
			}
		
			// save
			$this->db->where('historyid', $historyid);
			$this->db->update('history', array('contents' => $contents));
		}
		
		// echo
		echo true;
	}
	
	// update client
	function updateclient()
	{
		$idstr = $this->input->post('idarr');
			parse_str($idstr);
		$frmstr = $this->input->post('frm');
			parse_str($frmstr);
		$insarr = array_map('trim_to_null', $frm);
		
		//echo '<pre>'; print_r($sel); echo '</pre>';
		//echo '<pre>'; print_r($frm); echo '</pre>';
		
		// loop
		foreach($sel as $historyid){
			// get
			$hrs = $this->db->select('*')->from('history')->where('historyid', $historyid)->get()->row_array();
			
			// save
			$this->client_model->save($insarr, $hrs['clientid']);
		}
	}
	
	
	// set progress
	function setprogress()
	{
		$text = $this->input->post('text');
		
		set_progress($text);
	}
	
	
	// get progress
	function getprogress()
	{
		$data = get_progress();
		
		// return
		echo $data;
	}
	
	
	// get template
	function json_gettemplate($templateid, $clientid=null)
	{
		// check
		if(!$templateid){
			echo json_encode(array()); return;			
		}
		
		// get
		$templaters = $this->template_model->get($templateid);
		
		// clean
		$content = strip_tags($templaters['content']);
		$content = str_replace('&nbsp;', '', $content);
		
		// set
		$retarr = array('content' => $content);
		
		// fill
		$retarr['content'] = fill_template_contents($clientid, $retarr['content']);
		
		// return
		echo json_encode($retarr);
	}

	
	// get history
	function json_gethistory($historyid, $isemail=null)
	{
		// get
		$retarr = $this->history_model->get($historyid);
			
		// update
		if(!$isemail){
			// update
			if(stristr($retarr['workflowactionname'], 'section')){
				$retarr['contents'] = str_replace('%defaultaddress%', '%defaultprintaddress%', $retarr['contents']);

			}
		}
		
		// fill
		$retarr['contents'] = fill_template_contents($retarr['clientid'], $retarr['contents']);
		// die(var_dump($retarr['contents']));
		//echo $retarr['contents']; exit;
		
		// return
		echo json_encode($retarr);
	}

	
	// get attachments
	function json_getattachments($historyid)
	{
		// get
		$historyrs = $this->history_model->get($historyid);
		$attachmentrs = $this->attachment_model->get(null, array('a.clientid' => $historyrs['clientid']));
		
		// set
		$doctypearr = (company() && company()->schooltypeid == 1001) ? config_item('private-doctype-select-arr') : config_item('public-doctype-select-arr');
		
		$retarr = array();
		
		// loop
		foreach($attachmentrs as $row){
			// update
			$row['filetype'] = (isset($doctypearr[$row['doctype']])) ? $doctypearr[$row['doctype']] : 'Other';
		
			// set
			$row['downloadbtn'] = ($row['filename']) ? '<a href="'.$row['uploadsdir'].$row['filename'].'" target="_blank" class="btn btn-xs btn-success">Download</a>' : '';
			
			// append
			$retarr[] = $row;
		}
		
		//echo '<pre>'; print_r($retarr); echo '</pre>';
		
		// return
		echo json_encode($retarr);
	}
	
	
	// save history
	function json_savehistory()
	{
		// set
		$form = $this->input->post('form');
		
		// save
		$this->history_model->save(array('contents' => $form['contents']), $form['historyid']);
	}
	
	
	// e-mail todo
	function json_emailtodo()
	{
		// set
		$form = $this->input->post('form');
		$retarr = array('remidarr' => array());
		
		//$form['historyid'] = 52456;
		
		// get
		$historyrs = $this->history_model->get($form['historyid']);
		$clientrs = $this->client_model->get($historyrs['clientid']);
		$templaters = ($historyrs['templateid']) ? $this->template_model->get($historyrs['templateid']) : null;
		
		// set
		$from = (user()->email) ? user()->email : 'support@jumpingfoxsolutions.com';
		
		// check
		$languageid = langpref($clientrs['clientid']);
		
		// set
		$subject = fill_template_contents($clientrs['clientid'], 'Your 2016 school fee account / ACC REF:%finacccode%'); 
		$msg = 'Please find attached urgent communication for your attention.'; 
				
		// check
		if($templaters){
			if($languageid == 22){
				$subject = fill_template_contents($clientrs['clientid'], $templaters['emailsubject_eng']);
				$msg = fill_template_contents($clientrs['clientid'], $templaters['emailbody_eng']);
			}else{
				$subject = fill_template_contents($clientrs['clientid'], $templaters['emailsubject_afr']);
				$msg = fill_template_contents($clientrs['clientid'], $templaters['emailbody_afr']);
			}
		}
		
		// check
		if($historyrs['workflowactiontypeid'] && $clientrs['email'] && !stristr($historyrs['workflowactionname'], 'section')){
			// save
			$filepath = save_historydoc($historyrs, $clientrs, $form['contents']);
			//$filepath = base_url().ltrim($filepath, '/');
				
			// set
			$contents = fill_template_contents($historyrs['clientid'], $historyrs['contents']);
			
			// e-mail
			sendemail($clientrs['email'], $subject, $msg, $filepath, $from);
			
			// check
			if(!$templaters || ($templaters && $historyrs['isprinted'])){
				$this->history_model->save(array('historyactionid' => 28, 'contents' => $contents), $form['historyid']);
				
				$retarr['remidarr'][] = $form['historyid'];
			}
		}
		
		// save
		$this->history_model->save(array('isemailed' => 1), $form['historyid']);
		
		
		// echo
		echo json_encode($retarr);
	}
	
	// e-mail all
	function json_emailtodo_all()
	{
		// set
		$form = $this->input->post('sel');
		$totalrows = count($form);
		$retarr = array('remidarr' => array());
		$companyid = company()->companyid;
		
		// set
		$from = (user()->email) ? user()->email : 'support@jumpingfoxsolutions.com';
		
		// get
		$sapobatchrs = $this->sapobatch_model->get(null, array('sb.companyid' => $companyid), array('where-str' => "(sb.submittedon IS NULL)"), true);
		$sapobatchid = ($sapobatchrs) ? $sapobatchrs['sapobatchid'] : null;
		
		// check
		if(!$sapobatchid){
			// save
			$sapobatchid = $this->sapobatch_model->save(array('companyid' => $companyid));
		}
		
		// loop
		foreach($form as $i => $historyid){
			$num = $i+1;
						
			// set progress
			set_progress('Sending ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			// get
			$historyrs = $this->history_model->get($historyid);
			$clientrs = $this->client_model->get($historyrs['clientid']);
			$templaters = ($historyrs['templateid']) ? $this->template_model->get($historyrs['templateid']) : null;
			
			// check
			$languageid = langpref($clientrs['clientid']);
			
			// set
			$subject = fill_template_contents($clientrs['clientid'], 'Your 2017 school fee account / ACC REF:%finacccode%'); 
			$msg = 'Please find attached urgent communication for your attention.';
			$updarr = array('isemailed' => 1); 
				
			// check
			if($templaters){
				if($languageid == 22){
					$subject = fill_template_contents($clientrs['clientid'], $templaters['emailsubject_eng']);
					$msg = fill_template_contents($clientrs['clientid'], $templaters['emailbody_eng']);
				}else{
					$subject = fill_template_contents($clientrs['clientid'], $templaters['emailsubject_afr']);
					$msg = fill_template_contents($clientrs['clientid'], $templaters['emailbody_afr']);
				}
			}	
						
			// check
			//if($historyrs['workflowactiontypeid'] == 8 && $clientrs['email'] && !stristr($historyrs['workflowactionname'], 'section')){
			if($historyrs['workflowactiontypeid'] == 8 && $clientrs['email']){
				// save
				$filepath = save_historydoc($historyrs, $clientrs);
				
				// set
				$contents = fill_template_contents($historyrs['clientid'], $historyrs['contents']);
					
				// e-mail
				if(SAPO_ISENABLED && $historyrs['sendviaregisteredemail']){
					// send registered
					$updarr['sapobatchid'] = $sapobatchid;
				}else{
					// send 
					sendemail($clientrs['email'], $subject, $msg, $filepath, $from);
				}
				
				// check
				if(!$templaters || ($templaters && $historyrs['isprinted'])){
					$this->history_model->save(array('historyactionid' => 28, 'contents' => $contents), $historyid);
					
					$retarr['remidarr'][] = $historyid;
				}
			}
			
			// save
			$this->history_model->save($updarr, $historyid);
		}
		
		// update
		update_todostats();
		updatetoolstats();
		
		
		// echo
		echo json_encode($retarr);
	}
	
	
	// print todo
	function json_printtodo()
	{
		// set
		$form = $this->input->post('form');
		$retarr = array('remidarr' => array());
		
		//$form['historyid'] = 39671;
		
		// get
		$historyrs = $this->history_model->get($form['historyid']);
		$clientrs = $this->client_model->get($historyrs['clientid']);
		$templaters = ($historyrs['templateid']) ? $this->template_model->get($historyrs['templateid']) : null;
			
		// update
		$historyrs['contents'] = $form['contents'];
			
		// update
		if(stristr($historyrs['workflowactionname'], 'section')){
			$historyrs['contents'] = str_replace('%defaultaddress%', '%defaultprintaddress%', $historyrs['contents']);
		}
		
		// set
		$contents = $historyrs['contents'] = fill_template_contents($historyrs['clientid'], $historyrs['contents']);
		
		// create doc
		$filepath = save_historydoc($historyrs, $clientrs);
		$filepath = base_url().ltrim($filepath, '/');
	
		// save
		$this->history_model->save(array('isprinted' => 1), $form['historyid']);
		
		// check
		if(!$templaters || ($templaters && $historyrs['isemailed'])){
			$this->history_model->save(array('historyactionid' => 28, 'contents' => $contents), $form['historyid']);
			
			$retarr['remidarr'][] = $form['historyid'];
		}
		
		// update
		$retarr['filepath'] = $filepath;
		$retarr['filename'] = end(explode('/', $filepath));
		 
		// download
		//$this->load->helper('download');
		//force_download(end(explode('/', $filepath)), file_get_contents($filepath)); 
		
		// update
		update_todostats();
		updatetoolstats();
		
		
		// echo
		echo json_encode($retarr);
	}
	
	// print all
	function json_printtodo_all()
	{
		// set
		$form = $this->input->post('sel');
		$totalrows = count($form);
		$retarr = array('remidarr' => array());
		
		// loop
		foreach($form as $i => $historyid){
			$num = $i+1;
						
			// set progress
			set_progress('Saving ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			// get
			$historyrs = $this->history_model->get($historyid);
			$clientrs = $this->client_model->get($historyrs['clientid']);
			$templaters = ($historyrs['templateid']) ? $this->template_model->get($historyrs['templateid']) : null;
			
			// check
			if($historyrs['workflowactiontypeid'] == 8){
				// create doc
				$filepath = save_historydoc($historyrs, $clientrs);
				$filepath = base_url().ltrim($filepath, '/');
	
				// save
				$this->history_model->save(array('isprinted' => 1), $historyid);
					
				// update
				if(stristr($historyrs['workflowactionname'], 'section')){
					$historyrs['contents'] = str_replace('%defaultaddress%', '%defaultprintaddress%', $historyrs['contents']);
				}
					
				// set
				$contents = fill_template_contents($historyrs['clientid'], $historyrs['contents']);
				
				// check
				if(!$templaters || ($templaters && $historyrs['isemailed'])){
					$this->history_model->save(array('historyactionid' => 28, 'contents' => $contents), $historyid);
					
					$retarr['remidarr'][] = $historyid;
				}
							
				// echo
				$retarr['filearr'][] = array('filepath' => $filepath, 'filename' => end(explode('/', $filepath)));
			}
		}
		
		// update
		update_todostats();
		updatetoolstats();
		
		
		// echo
		echo json_encode($retarr);
	}
	
	
	// delete clients
	function json_delclients()
	{
		// set
		$form = $this->input->post('frm');
		$idarr = array_filter( explode('|', trim($form['idstr'])) );
		$totalrows = count($idarr);
		
		// email
		$subject = 'Bulk Debtor Deletion Report';
		$msg = '<p>The following debtors were deleted by: '.user()->firstname.' '.user()->lastname.'.</p>';
		$from = (company()->supervisoremail) ? company()->supervisoremail : EMAIL_DEBUGWORKFLOW;

		// loop
		$msg .= '<p>';
		foreach($idarr as $num => $clientid){
			// get
			$clientrs = $this->client_model->getmin($clientid);
						
			// set progress
			set_progress('Deleting ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			// msg
			$msg .= $clientrs['finacccode'].': '.$clientrs['fullname'].'<br />';
			
			// delete
			$this->db->where('clientid', $clientid)->delete('history');
			$this->db->where('clientid', $clientid)->delete('fintx');
			$this->db->where('clientid', $clientid)->delete('tx');
			$this->db->where('clientid', $clientid)->delete('log');
			$this->db->where('clientid', $clientid)->delete('attachment');
			
			// delete
			$this->client_model->delete($clientid, true);
			
			// save
			$insarr = array(
					'historytypeid' => 26,
					'clientid' => $clientid,
					'contents' => $clientrs['finacccode'].': '.$clientrs['fullname'].' was deleted.'
				);
			$this->history_model->save($insarr);
		}
		$msg .= '</p>';
		
		// e-mail
		sendemail($form['email'], $subject, $msg, null, $from);
		
		
		// echo
		echo json_encode(array('success' => 1));
	}
	
	
	// update clients
	function json_updclients()
	{
		// set
		$form = array_map('trim', $this->input->post('frm'));
		$idarr = array_filter( explode('|', trim($form['idstr'])) );
		$totalrows = count($idarr);
		
		// unserty
		unset($form['idstr']);
		
		// clean
		/*foreach($form as $key => $val){
			$form[$key] = (trim($val) && $val) ? $val : 'null';
		}*/
		
		// loop
		foreach($idarr as $num => $clientid){
			$insarr = array();
				$insarr['lastworkflowactionid'] = ($form['lastworkflowactionid']) ? $form['lastworkflowactionid'] : 'null';
				$insarr['bflastworkflowactionid'] = ($form['bflastworkflowactionid']) ? $form['bflastworkflowactionid'] : 'null';
				$insarr['lastworkflowactionon'] = ($form['lastworkflowactionid']) ? date("Y-m-d H:i:s") : 'null';
				$insarr['bflastworkflowactionon'] = ($form['bflastworkflowactionid']) ? date("Y-m-d H:i:s") : 'null';
				$insarr['clientstatusid'] = ($form['clientstatusid']) ? $form['clientstatusid'] : 'null';
				
			//echo '<pre>'; print_r($insarr); echo '</pre>'; exit;
			// set progress
			set_progress('Updating ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			// save
			$this->client_model->save($form, $clientid);
			
			// save
			$insarr = array(
					'historytypeid' => 26,
					'clientid' => $clientid,
					'contents' => 'The debtor status was deleted/removed.'
				);
			$this->history_model->save($insarr);
		}
		
		
		// echo
		echo json_encode(array('success' => 1));
	}
	
	
	// skip todos
	function json_skiptodos($idarr, $return=true)
	{
		// set
		$companyid = company()->companyid;
		$userid = user()->userid;
		$idarr = ($idarr) ? $idarr : $this->input->post('sel');
		
		// check other
		foreach($idarr as $num => $historyid){
			// get
			$historyrs = $this->history_model->get($historyid);
			
			// get
			$historyotherrs = $this->history_model->get(null, array('h.historyid !=' => $historyid, 'h.clientid' => $historyrs['clientid'], 'h.historyactionid' => 27), array('where-str' => 'h.templateid IS NOT NULL'));
			
			// check
			if($historyotherrs){
				// loop
				foreach($historyotherrs as $row){
					if(!in_array($row['historyid'], $idarr)){
						$idarr[] = $row['historyid'];
					}					
				}
			}
		}
		
		// set
		$totalrows = count($idarr);
		
		
		// loop
		foreach($idarr as $num => $historyid){
						
			// set progress
			set_progress('Updating ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			$insarr = array(
					'companyid' => $companyid,
					'audittypeid' => 17,
					'userid' => $userid,
					'historyid' => $historyid,
					'entryname' => 'Notice Skipped'
				);
			// save
			$this->audit_model->save($insarr);
			
			// update
			$this->history_model->save(array('historyactionid' => 57), $historyid);
			
			// get
			$historyrs = $this->history_model->get($historyid);
			$clientrs = $this->client_model->get($historyrs['clientid']);
			
			// update
			$this->client_model->save(array('disabledaysdelay' => 1), $clientrs['clientid']);		
		}
		
		// update
		update_todostats();
		
		
		// echo
		if($return){
			echo json_encode(array('success' => 1));
		}
	}	
	
	
	// cancel todos
	function json_canceltodos($idarr=null, $return=true)
	{
		// set
		$companyid = company()->companyid;
		$userid = user()->userid;
		$idarr = ($idarr) ? $idarr : $this->input->post('sel');
		
		// check other
		foreach($idarr as $num => $historyid){
			// get
			$historyrs = $this->history_model->get($historyid);
			
			// get
			$historyotherrs = $this->history_model->get(null, array('h.historyid !=' => $historyid, 'h.clientid' => $historyrs['clientid'], 'h.historyactionid' => 27), array('where-str' => 'h.templateid IS NOT NULL'));
			
			// check
			if($historyotherrs){
				// loop
				foreach($historyotherrs as $row){
					if(!in_array($row['historyid'], $idarr)){
						$idarr[] = $row['historyid'];
					}					
				}
			}
		}
		
		// set
		$totalrows = count($idarr);
		
		// loop
		foreach($idarr as $num => $historyid){
						
			// set progress
			set_progress('Updating ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
			
			$insarr = array(
					'companyid' => $companyid,
					'audittypeid' => 17,
					'userid' => $userid,
					'historyid' => $historyid,
					'entryname' => 'Notice Cancelled'
				);
			// save
			$this->audit_model->save($insarr);
			
			// update
			$this->history_model->save(array('historyactionid' => 29), $historyid);
			
			// revert
			$historyrs = $this->history_model->get($historyid);
			$clientrs = $this->client_model->get($historyrs['clientid']);
			
			// check
			if(trim($clientrs['historyarr'])){
				$arr = json_decode($clientrs['historyarr'], true);
				
				$this->db->where('clientid', $clientrs['clientid']);
				$this->db->update('client', $arr);
			}
		
		}
		
		// update
		update_todostats();
		
		
		// echo
		if($return){
			echo json_encode(array('success' => 1));
		}
	}
	
	
	// send todos
	function json_sendtodos()
	{
		// set
		$companyid = company()->companyid;
		$userid = user()->userid;
		$idarr = $this->input->post('sel');
		
		// get
		$companyrs = $this->company_model->get($companyid);
		$where = 'h.historyid IN ('.implode(',', $idarr).') AND wfa.workflowactiontypeid=7';
		$historyrs = $this->history_model->get(null, null, array('where-str' => $where));
		$totalrows = count($historyrs);
		
		// check
		/*if($companyrs['creditbalance'] < $totalrows){
			echo json_encode(array('errormsg' => 'Not enough credits left to send all the messages.')); exit;
		}*/
		
		// init
		$sms = new Sms();
		
		// loop
		foreach($historyrs as $i => $row){
			$num = $i+1;
						
			// set progress
			set_progress('Updating ('.$num.' of '.$totalrows.')... %'.round( ($num*100)/$totalrows ));
				
			// set
			$contents = fill_template_contents($row['clientid'], $row['contents'], true);
			$cellarr = array_unique(array_filter(array_map('trim', array($row['mobile'], $row['mobile2']))));
			
			// loop
			foreach($cellarr as $cell){
				$txid = billcredit(14, $row['clientid']);
			
				// send
				$smsresult = $sms->SendSMS(array($cell), $contents, $txid);
				
				// save
				$insarr = array(
						'companyid' => $companyid,
						'audittypeid' => 10,
						'userid' => $userid,
						'historyid' => $row['historyid'],
						'entryname' => 'Send SMS',
						'notes' => $contents
					);
				$this->audit_model->save($insarr);
				
				// set
				$smsparts = ceil(strlen($contents)/SMS_LENGTH);
				
				// update
				$this->history_model->save(array('historyactionid' => 28, 'contents' => $contents), $row['historyid']);
				$this->tx_model->save(array('description' => $contents.' '.$smsresult, 'smsparts' => $smsparts), $txid);
			}			
		}
		
		// update
		update_todostats();
		updatetoolstats();
		
		
		// echo
		echo json_encode(array('success' => 1));
	}
	
	
	// json upload
	function json_uploadfile($historyid)
	{
		// get
		$historyrs = $this->history_model->get($historyid);
		$attachmentrs = $this->attachment_model->get(null, array('a.clientid' => $historyrs['clientid']));
		
		// check
		$uploadsdir = $this->utils->check_client_dir($historyrs['clientid']);
		
		// upload
		$filename = upload_file('docfile', $uploadsdir);
		
		// check
		if($filename){
			$docform = $this->input->post('docform');
			
			// update
			$docform['historyid'] = $historyid;
			$docform['clientid'] = $historyrs['clientid'];
			$docform['filename'] = $filename;
			
			// save
			$this->attachment_model->save($docform);
		}
	}
	
	
	// json get kb
	function json_getkb($kbid)
	{
		// get
		$rs = $this->db->select('*')->from('kb')->where('kbid', $kbid)->get()->row_array();
		
		echo json_encode($rs);
	}		
	
	
	// json get client attachments
	function json_getclientattachments($clientid)
	{
		// get
		$rs = $this->attachment_model->get(null, array('a.clientid' => $clientid));
		
		echo json_encode($rs);
	}
	
	
	// json get attachment
	function json_getattachment($attachmentid)
	{
		// get
		$rs = $this->attachment_model->get($attachmentid);
		
		echo json_encode($rs);
	}
		
		
	// json get arrangement
	function json_getarrangement($arrangementid)
	{
		// get
		$rs = $this->arrangement_model->get($arrangementid);
		
		// check
		/*if($rs['startdate'] < date("Y-m-d")){
			$rs['fromyear'] = date("Y");
			$rs['frommonth'] = date("n");
		}*/
		if($rs['frommonth'] < date("n")){
			$rs['frommonth'] = date("n");
		}
		if($rs['fromyear'] < date("Y")){
			$rs['fromyear'] = date("Y");
		}
		
		// update
		$rs['frommonth'] = $rs['fromyear'].'|'.str_pad($rs['frommonth'], 2, 0, STR_PAD_LEFT); 
		$rs['tomonth'] = $rs['toyear'].'|'.str_pad($rs['tomonth'], 2, 0, STR_PAD_LEFT); 
		
		echo json_encode($rs);
	}
		
	
	// json save arrangement
	function json_savearrangement()
	{
		// post
		$form = $this->input->post('form');
		$form['startdate'] = $this->input->post('startdate');
		$form['includesbf'] = 1;
		$autoarrjson = array();
		$autoarrangementjson = null;
		
		// get
		$arrangementrs = $this->arrangement_model->get(null, array('a.clientid' => $form['clientid']));
		
		// delete
		if($arrangementrs){
			// loop
			foreach($arrangementrs as $arrrow){
				$arrangementid = $arrrow['arrangementid'];
				
				// set
				$autoarrangementjson = $arrrow['autoarrangementjson'];
				
				// check
				if($arrrow['isaautoarrangement']){
					// get
					$fintxrs = $this->db->select('*')->from('fintx')->where('arrangementid', $arrangementid)->get()->result_array();
					
					// update
					$autoarrjson['arrangementrs'] = $arrrow;
					$autoarrjson['fintxrs'] = $fintxrs; 
				}
				
				// delete
				$this->db->where('arrangementid', $arrangementid);
				$this->db->delete('fintx');
				$this->arrangement_model->delete($arrangementid, 1);
			}
		}
		
		// update
		list($fromyear, $frommonth) = explode('|', $form['frommonth']);
		$form['fromyear'] = $fromyear;
		$form['frommonth'] = $frommonth;
		list($toyear, $tomonth) = explode('|', $form['tomonth']);
		$form['toyear'] = $toyear;
		$form['tomonth'] = $tomonth;
		
		// check
		if($autoarrangementjson){
			$form['autoarrangementjson'] = $autoarrangementjson;
		}
		if(isset($autoarrjson['arrangementrs'])){
			$form['autoarrangementjson'] = json_encode($autoarrjson);
		}
		
		// save
		$arrangementid = $this->arrangement_model->save($form);
		
		// set
		$companyid = company()->companyid;
		$amount = $form['amount'];
		$paymentday = $form['paymentday'];
		$fromdate = $form['startdate'];
		//echo $fromdate.' - ';
		$startmonth = date("n", strtotime($fromdate));
		$startday = date("j", strtotime($fromdate));
		$clientinparr = array();
		
		$arrfromdate = "$fromyear-$frommonth-$paymentday";
		
		$arrfromdate = (date("t", strtotime("$fromyear-$frommonth-01")) < $paymentday) ? "$fromyear-$frommonth-".date("t", strtotime("$fromyear-$frommonth-01")) : $arrfromdate;
		$arrtodate = "$toyear-$tomonth-$paymentday";
		$arrtodate = (date("t", strtotime("$toyear-$tomonth-01")) < $paymentday) ? "$toyear-$tomonth-".date("t", strtotime("$toyear-$tomonth-01")) : $arrtodate;
		$arrworkdate = date("Y-m-01", strtotime($arrfromdate));
		//echo $arrworkdate.' - ';

		$frommonth = date("n", strtotime($arrfromdate));
		$tomonth = date("n", strtotime($arrtodate));
		$tomonth = (date("Y", strtotime($arrtodate)) > date("Y")) ? (12-$frommonth+$tomonth+1) : $tomonth;
		$months = $tomonth-$frommonth+1;
		$payment = round($amount/$months, 2);
		
		// add entries
		while($arrworkdate <= $arrtodate){
			$numdays = date("t", strtotime($arrworkdate));
			$numdays = ($paymentday > $numdays) ? $numdays : $paymentday;
			$numdays = (date("n", strtotime($arrworkdate)) == $startmonth && date("j", strtotime($fromdate)) > $paymentday) ? $startday : $numdays;
			
			//echo $numdays.' - '.date("n", strtotime($arrworkdate)).' == '.$startmonth.' && '.date("j", strtotime($fromdate)).' > '.$paymentday.' = '.$startday; exit;
			
			$date = date("Y-m-$numdays", strtotime($arrworkdate));
			$month = date("n", strtotime("$date")); 
			$day = $numdays;
			
			//echo "$arrfromdate - $arrtodate = $date \n";
			
			// set
			$insarr = array(
					'companyid' => $companyid, 
					'clientid' => $form['clientid'],
					'arrangementid' => $arrangementid,
					'fintxtypeid' => 71,
					'findate' => $date,
					//'finentry' => null,
					//'finref' => null,
					'fincontra' => 'ARR',
					'findesc' => $form['title'],
					'findebit' => $payment,
					'fincredit' => 0 
					//'fincum' =>
				);
			
			// save
			$this->db->insert('fintx', $insarr);
			
			$clientinparr['epmonth'.ltrim($month, 0).'day'] = $day;
			
			// update
			$arrworkdate = date("Y-m-d", strtotime("$arrworkdate +1 month"));
		}
		
		// check
		if($form['includesbf'] == 1){
			$obfintxrs = $this->db->select('*')->from('fintx')->where('clientid', $form['clientid'])->where('fintxtypeid', 18)->get()->row_array();
				
			// update
			if($obfintxrs){
				$this->db->where('fintxid', $obfintxrs['fintxid']);
				$this->db->update('fintx', array('findebit' => 0, 'fincredit' => 0));
				
				// save
				$this->arrangement_model->save(array('openingdebit' => $obfintxrs['findebit'], 'openingcredit' => $obfintxrs['fincredit']), $arrangementid);
			}			
		}
		
		// get
		$fintxtotalrs = $this->db->select('SUM(findebit) AS totaldebit, SUM(fincredit) AS totalcredit')->from('fintx')->where('clientid', $form['clientid'])->where("(findate>='$fromdate' AND (fintxtypeid=18 OR fintxtypeid=20 OR fintxtypeid=71))")->get()->row_array();
		$fintxrs = $this->db->select('*')->from('fintx')->where('clientid', $form['clientid'])->where('fintxtypeid', 19)->get()->row_array();
		
		// check
		switch($form['type']){
			case 'ptp':
				$clientinparr['ispromisetopay'] = 1;
			break;
			case 'aod':
				$clientinparr['isaod'] = 1;
			break;
			case 'do':
				$clientinparr['isdebitorder'] = 1;
			break;
			case 'subsidy':
				$clientinparr['issubsidy'] = 1;
			break;
		}
		
		// update
		$this->client_model->save($clientinparr, $form['clientid']);
		$this->db->where('fintxid', $fintxrs['fintxid']);
		//$this->db->update('fintx', array('findebit' => $fintxtotalrs['totaldebit'], 'fincredit' => $fintxtotalrs['totalcredit']));
		$this->db->update('fintx', array('findebit' => $fintxtotalrs['totaldebit'], 'fincredit' => $fintxtotalrs['totalcredit']));
	}
	
	
	// json update entries
	function json_updatearrangement_entries()
	{
		// set
		$form = $this->input->post('arrform');
		
		// loop
		foreach($form as $fintxid => $row){
			// get
			$fintxrs = $this->fintx_model->get($fintxid);
			
			// check
			if($fintxrs['startdate'] > $row['findate']){
				$row['findate'] = date("Y-m-d", strtotime($fintxrs['startdate']." +1 day"));
			}
			
			// save
			$this->db->where('fintxid', $fintxid);
			$this->db->update('fintx', $row);
		}
		
		// get
		$fintxrs = $this->fintx_model->get($fintxid);
		$arrangementrs = $this->arrangement_model->get($fintxrs['arrangementid']);
		$fintxtotalrs = $this->db->select('SUM(findebit) AS totaldebit')->from('fintx')->where('arrangementid', $arrangementrs['arrangementid'])->get()->row_array();
		
		// update
		$this->arrangement_model->save(array('amount' => $fintxtotalrs['totaldebit']), $arrangementrs['arrangementid']);
	}
	
	
	// json delete arrangement
	function json_delarrangement($arrangementid)
	{
		// get
		$arrangementrs = $this->arrangement_model->get($arrangementid);
		
		// delete
		$this->arrangement_model->delete($arrangementid, 1);
		$this->db->where('arrangementid', $arrangementid);
		$this->db->delete('fintx');
		
		// restore
		if($arrangementrs['autoarrangementjson']){
			$autoarrjson = json_decode($arrangementrs['autoarrangementjson'], true);
			$fintxarr = $autoarrjson['fintxrs'];
			
			// clean
			unset($autoarrjson['arrangementrs']['arrangementid']);
			unset($autoarrjson['arrangementrs']['deleted']);
			
			// save
			$arrangementid = $this->arrangement_model->save($autoarrjson['arrangementrs']);
			
			// loop
			foreach($fintxarr as $insrow){
				// clean
				unset($insrow['fintxid']);
				
				// update
				$insrow['arrangementid'] = $arrangementid;
				
				// save
				$this->db->insert('fintx', $insrow);				
			}
		}
		
		// check
		if($arrangementrs['includesbf']){
			$obfintxrs = $this->db->select('*')->from('fintx')->where('clientid', $arrangementrs['clientid'])->where('fintxtypeid', 18)->get()->row_array();
				
			// update
			if($obfintxrs){
				$this->db->where('fintxid', $obfintxrs['fintxid']);
				$this->db->update('fintx', array('findebit' => $arrangementrs['openingdebit'], 'fincredit' => $arrangementrs['openingcredit']));
			}
		}
		
		// get
		$fintxtotalrs = $this->db->select('SUM(findebit) AS totaldebit, SUM(fincredit) AS totalcredit')->from('fintx')->where('clientid', $arrangementrs['clientid'])->where("(fintxtypeid=18 OR fintxtypeid=20 OR fintxtypeid=71)")->get()->row_array();
		$fintxrs = $this->db->select('*')->from('fintx')->where('clientid', $arrangementrs['clientid'])->where('fintxtypeid', 19)->get()->row_array();
		
		// update
		$this->db->where('fintxid', $fintxrs['fintxid']);
		$this->db->update('fintx', array('findebit' => $fintxtotalrs['totaldebit'], 'fincredit' => $fintxtotalrs['totalcredit']));
	}
	
	
	// get payment days
	function json_getpaymentdays($clientid, $retarr=array())
	{
		// get
		$clientrs = $this->client_model->get($clientid);
		
		// loop
		foreach($clientrs as $key => $val){
			// check
			if(stristr($key, 'epmonth') && stristr($key, 'day')){
				$title = str_replace(array('epmonth', 'day'), '', $key);
				$title = date("Y-".str_pad($title, 2, 0, STR_PAD_LEFT)).'-day';
				
				$retarr[$title] = ($val) ? $val : 1;
			}
		}
		
		//echo '<pre>'; print_r($retarr); echo '</pre>'; exit;
		
		// save
		echo json_encode($retarr);
	}
	
	// save payment days
	function json_savepaymentdays()
	{
		// set
		$form = $this->input->post('form');
		
		// get
		$arrangementrs = $this->arrangement_model->get(null, array('a.clientid' => $form['clientid']), null, true);
		
		// set
		$clientid = $form['clientid'];
		$inparr = array();
		
		// loop
		foreach($form as $key => $day){
			// check
			if(stristr($key, '-day')){
				list($year, $month, $str) = explode('-', $key);
				
				// clen
				$month = ltrim($month, 0);
				
				// check
				if($arrangementrs && $arrangementrs['fromyear'] == $year && $arrangementrs['frommonth'] == $month){
					// check
					if($day < date("j", strtotime($arrangementrs['startdate']))){
						$day = date("j", strtotime($arrangementrs['startdate']." +1 day"));
					}
				}
				
				$inparr['epmonth'.$month.'day'] = $day;
			}
		}
		
		// save
		$this->client_model->save($inparr, $clientid);
	}
	
	
	// save manual arrangement 
	function json_savemanualarrangement()
	{
		// set
		$companyid = company()->companyid;
		$form = $this->input->post('form');
		$form['startdate'] = $this->input->post('startdate');
		$finform = $this->input->post('finform');
		
		//echo '<pre>'; print_r($form); echo '</pre>';
		//echo '<pre>'; print_r($finform); echo '</pre>'; exit;
	
		// get
		$arrangementrs = $this->arrangement_model->get(null, array('a.clientid' => $form['clientid']));
		
		// delete
		if($arrangementrs){
			// loop
			foreach($arrangementrs as $arrrow){
				$arrangementid = $arrrow['arrangementid'];
				
				// set
				$autoarrangementjson = $arrrow['autoarrangementjson'];
				
				// check
				if($arrrow['isaautoarrangement']){
					// get
					$fintxrs = $this->db->select('*')->from('fintx')->where('arrangementid', $arrangementid)->get()->result_array();
					
					// update
					$autoarrjson['arrangementrs'] = $arrrow;
					$autoarrjson['fintxrs'] = $fintxrs; 
				}
				
				// delete
				$this->db->where('arrangementid', $arrangementid);
				$this->db->delete('fintx');
				$this->arrangement_model->delete($arrangementid, 1);
			}
		}
		
		// set
		$autoarrangementjson = (isset($autoarrangementjson)) ? $autoarrangementjson : null;
		$autoarrangementjson = (isset($autoarrjson)) ? json_encode($autoarrjson) : $autoarrangementjson;
		
		// get
		$obfintxrs = $this->db->select('*')->from('fintx')->where('clientid', $form['clientid'])->where('fintxtypeid', 18)->get()->row_array();
		$findebit = 0;
		$fincredit = 0;
				
		// update
		if($obfintxrs){
			$findebit = $obfintxrs['findebit'];
			$fincredit = $obfintxrs['fincredit'];
			
			// update	
			$this->db->where('fintxid', $obfintxrs['fintxid']);
			$this->db->update('fintx', array('findebit' => 0, 'fincredit' => 0));
		}
		
		// set
		$clientid = $form['clientid'];
		$inparr = array(
				'clientid' => $form['clientid'],
				'title' => 'Manual-Arrangement',
				'amount' => 0,
				'frommonth' => null,
				'fromyear' => null,
				'tomonth' => null,
				'toyear' => null,
				'paymentday' => 1,
				'type' => $form['type'],
				'includesbf' => 1,
				'openingdebit' => $findebit,
				'openingcredit' => $fincredit,
				'ismanualarrangement' => 1,
				'startdate' => null,
				'autoarrangementjson' => $autoarrangementjson,
			);
		$fininparr = array();
		$clientinparr = array();
		
		// update
		$inparr['startdate'] = $form['startdate'];
		$inparr['frommonth'] = date("n", strtotime($inparr['startdate']));
		$inparr['fromyear'] = date("Y", strtotime($inparr['startdate']));
		
		// loop
		foreach($finform as $year => $finrow){
			/// loop
			foreach($finrow as $month => $row){
				$day = $row['day'];
				$amt = $row['amt'];
				$date = date("Y-m-d", strtotime("$year-$month-$day"));
				
				// check
				if($day && $amt > 0){
					$inparr['tomonth'] = $month;
					$inparr['toyear'] = $year;
					$inparr['amount'] += $amt;
					
					$fininparr[] = array(
						'companyid' => $companyid, 
						'clientid' => $form['clientid'],
						//'arrangementid' => $arrangementid,
						'fintxtypeid' => 71,
						'findate' => $date,
						'finentry' => 'Manual-Arrangement',
						//'finref' => null,
						'fincontra' => 'MARR',
						'findesc' => 'Manual-Arrangement',
						'findebit' => $amt,
						'fincredit' => 0 
						//'fincum' =>
					);
					
					$clientinparr['epmonth'.ltrim($month, 0).'day'] = $day;
				}
			}
		}
		
		// check
		switch($form['type']){
			case 'ptp':
				$clientinparr['ispromisetopay'] = 1;
			break;
			case 'aod':
				$clientinparr['isaod'] = 1;
			break;
			case 'do':
				$clientinparr['isdebitorder'] = 1;
			break;
			case 'subsidy':
				$clientinparr['issubsidy'] = 1;
			break;
		}
		
		// save
		$this->client_model->save($clientinparr, $form['clientid']);
		$arrangementid = $this->arrangement_model->save($inparr);
		
		// loop
		foreach($fininparr as $row){
			$row['arrangementid'] = $arrangementid;
			
			// save
			$this->db->insert('fintx', $row);
		}
		
		/*echo '<pre>'; print_r($inparr); echo '</pre>';
		echo '<pre>'; print_r($fininparr); echo '</pre>'; exit;*/
	}
	
	
	// archive clients
	function json_archiveclients($action)
	{
		// set
		$idarr = $this->input->post('sel');
		
		// loop
		foreach($idarr as $clientid){
			// get
			$clientrs = $this->client_model->get($clientid);
			
			// set default
			$inparr['archivedon'] = 'null';
			
			// check
			if($action == 1){
				$inparr['archivedon'] = ($clientrs['archivedon']) ? $clientrs['archivedon'] : date("Y-m-d H:i:s");
			}
			if($action == 0){
				if($clientrs['archivedon']){
					$inparr['disablearchiving'] = 1;
				}
			}
				
			// save
			$this->client_model->save($inparr, $clientid);
		}
		
		// echo
		echo json_encode(array('status' => 1));
	}

	
	// json get cell numbers
	function json_getcellnumbers()
	{
		// posty
		$fincodearr = $this->input->post('fincodearr');
		
		// set
		$retarr = array();
		
		// loop
		foreach($fincodearr as $clientid){
			// get
			$clientrs = $this->client_model->get($clientid);
			
			$retarr[] = str_replace(array(' ' , ';'), array('', ','), $clientrs['mobile']);
			$retarr[] = str_replace(array(' ' , ';'), array('', ','), $clientrs['mobile2']);
		}
		
		// clean
		$retarr = array_unique(array_filter(array_map('trim', $retarr)));
		
		// implode
		$str = implode(', ', $retarr);
		
		// echo
		echo $str;
	}
}

/* End of file ajax.php */
/* Location: ./application/controllers/ajax.php */