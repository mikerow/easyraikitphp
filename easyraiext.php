<?php

	/*

	easyraiextphp

	Allows you to perform some advanced operation not available with RPC
	
	====================

	LICENSE: Use it as you want!

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	====================

	*/

	// *******************
	// USAGE OF THE SCRIPT
	// *******************

	/*
	
	To use this extension add after include_once('PATH/easyraiblocks.php');
	This: include_once('PATH/easyraiext.php');
	
	*/
	
	$rb_ext = // Put here your variable name used to call RPC, Example: $raiblocks = new RaiBlocks('host','port');
	
	// ************************************************************
	// DO NOT EDIT BELOW, BUT DO IT IF YOU KNOW WHAT YOU ARE DOING!
	// ************************************************************
	
	// Call this function to get all balances of every accounts in a wallet
	// Parameters:
	// $walletID -> the ID of the wallet you want to check
	
	function raiblocks_balance_wallet($walletID){
	
		global $rb_ext;
		$accounts_balances = array( "sum_balance" => 0, "sum_pending" => 0, "accounts" => array() );
		
		$return = $rb_ext->account_list( args( "wallet" => $walletID ) ); // Get all accounts of a wallet
		
		// Fetch every account
		
		foreach($return["accounts"] as $account){
		
			$return2 = $rb_ext->account_balance( args( "account" => $account ) ); // Get balance of account
			
			$accounts_balances["accounts"][$account] = array( // Build the return array
			
				"balance_rai" => floor( $return2["balance"]/RAIN ),
				"pending_rai" => floor( $return2["pending"]/RAIN )
			
			);
			
			$accounts_balances["sum_balance_rai"] += $accounts_balances["accounts"][$account]["balance_rai"];
			$accounts_balances["sum_pending_rai"] += $accounts_balances["accounts"][$account]["pending_rai"];
		
		}
		
		return $accounts_balances;
	
	}
	
	// Call this function to clear a wallet sending all funds to an account
	// Parameters:
	// $walletID -> the ID of the wallet you want to clear
	// $destination -> the account that receive all funds
	
	function raiblocks_clear_wallet( $walletID, $destination ){
	
		global $rb_ext;
		$payment_hashes = array( "sum_balance_rai" => 0, "sum_paid_rai" => 0, "accounts" => array() );
		
		$return = raiblocks_balance_wallet($walletID);
		
		$payment_hashes["sum_balance_rai"] = $return["sum_balance_rai"];
		
		foreach( $return["accounts"] as $account => $balance ){
			
			if( $balance["balance_rai"] > 0 ){
			
				$args = array(
				
					"wallet" => $walletID,
					"source" => $account,
					"destination" => $destination,
					"amount" => $balance["balance_rai"].RAI
				
				);
				
				$return2 = $rb_ext->send( $args );
				
				if( $return2["block"] != "0000000000000000000000000000000000000000000000000000000000000000" ){ // If payment performed correctly
				
					$payment_hashes["accounts"][$account] = array(
					
						"hash" => $return2["block"],
						"amount" => $balance["balance_rai"]
					
					);
					
					$payment_hashes["sum_paid_rai"] += $balance["balance_rai"];
				
				}else{ // If error happened
				
					$payment_hashes["accounts"][$account] = array(
					
						"hash" => "error",
						"amount" => $balance["balance_rai"]
					
					);
				
				}
			
			}
			
		}
		
		return $payment_hashes;
	
	}
	
	// Call this function to send funds from a wallet to an account without sending from a particular account
	// Parameters:
	// $walletID -> the ID of the wallet you want to use as soruce of payment
	// $destination -> the account to send funds
	// $amount -> the funds you want to send (rai)
	
	function raiblocks_send_wallet( $walletID, $destination, $amount ){
	
		global $rb_ext;
		$payment_hashes = array( "status" => "ok", "sum_paid_rai" => 0, "accounts" => array() ); $selected_accounts = array(); $sum = 0; $diff_amount = $amount;
		
		$return = raiblocks_balance_wallet($walletID);
		
		// Select funds from accounts
		
		foreach($return["accounts"] as $account => $balance){
		
			if( $balance["balance_rai"] > 0 ){
			
				$selected_accounts[$account] = $balance["balance_rai"];
				$sum += $balance["balance_rai"];
			
			}else{
			
				continue;
			
			}
			
			if($sum >= $amount) break; // Amount reached?
		
		}
		
		// Sum not reached?
		
		if( $sum < $amount ){
		
			$payment_hashes["sum_paid_rai"] = 0;
			$payment_hashes["status"] = "not enough funds";
			return $payment_hashes;
		
		}
		
		// Sum reached?
		
		foreach($selected_accounts as $selected_account => $balance){
			
			if( $diff_amount - $balance < 0 ){
			
				$balance = $diff_amount;
			
			}else{
			
				// Nothing.
			
			}
			
			$args = array(
			
				"wallet" => $walletID,
				"source" => $selected_account,
				"destination" => $destination,
				"amount" => $balance.RAI
			
			);
			
			$return2 = $rb_ext->send( $args );
			
			if( $return2["block"] != "0000000000000000000000000000000000000000000000000000000000000000" ){ // If payment performed correctly
			
				$payment_hashes["accounts"][$selected_account] = array(
				
					"hash" => $return2["block"],
					"amount" => $balance
				
				);
				
				$payment_hashes["sum_paid_rai"] += $balance;
				
				$diff_amount -= $balance;
			
			}else{ // If error happened
			
				$payment_hashes["accounts"][$selected_account] = array(
				
					"hash" => "error",
					"amount" => $balance
				
				);
				
				$payment_hashes["status"] = "error";
			
			}
		
		}
		
		return $payment_hashes;
		
	}
	
	// Call this function to change the representative for every account that exist in the wallet and for further (if selected)
	// Parameters:
	// $walletID -> the ID of the wallet that contains your accounts
	// $representative -> the representative you want to set
	// $further -> change the representative of wallet for further accounts (default set to yes)
	
	function raiblocks_representative_all( $walletID, $representative, $further = true ){
		
		global $rb_ext;
		$rep_change = array( "further" => "no", "status" => "ok", "accounts" => array() );
		
		if($further){ // If change representative for further accounts
			
			$args = array(
			
				"wallet" => $walletID,
				"representative" => $representative
				
			);
			
			$return = $rbc->wallet_representative_set( $args );
			
			if( $return["set"] == "1" ){ // If set correctly
				
				$rep_change["further"] = "yes";
			
			}
		
		}
		
		$return = raiblocks_balance_wallet($walletID);
		
		// Change for each account
		
		foreach($return["accounts"] as $account => $balance){
		
			$args = array(
			
				"wallet" => $walletID,
				"acccount" => $account,
				"representative" => $representative
			
			);
		
			$return2 = $rbc->account_representative_set( $args );
			
			if( $return2["block"] != "0000000000000000000000000000000000000000000000000000000000000000" ){ // If change representative performed correctly
			
				$rep_change["accounts"][$account] = $return2["block"];
			
			}else{
			
				$rep_change["accounts"][$account] = "error";
				$rep_change["status"] = "error";
				
			}
		
		}
		
		return $rep_change;
		
	}
	
?>