<?php

namespace App\Http\Services;

use App\Http\Models\BalanceTransaction;
use App\Http\Models\Bet;
use App\Http\Models\BetSelection;
use App\Http\Models\Player;
use DB;

class BetService extends BaseService
{
    // Defined all global variables and constants
    private $player_id;
    private $is_int;
    private $odds_multiples = [];
    private $stake_amount;
    private $odds_validation;
    private $stake_validation;
    private $selections_validation;
    private $max_win_amount_validation;
    private $previous_action_validation = null;
    private $insufficient_validation = null;
    private $unknown_error_validation = null;
    private $structure_mismatch_validation = null;
    private $selections = [];
    private $errors = [];
    private $global_errors = [];
    private $selection_errors = [];
    private $error_object = [];
    private $errors_at_select = [];
    private $player = [];
    private const UNKNOWN_ERROR = "UNKNOWN_ERROR";
    private const STRUCTURE_MISMATCH = "STRUCTURE_MISMATCH";
    private const STAKE = "STAKE";
    private const STAKE_MIN_VALUE = 0.3;
    private const STAKE_MAX_VALUE = 10000;
    private const SELECTIONS = 'SELECTIONS';
    private const DUPLICATE_SELECTIONS = 'DUPLICATE_SELECTIONS';
    private const SELECTIONS_MIN_VALUE = 1;
    private const SELECTIONS_MAX_VALUE = 20;
    private const ODDS = 'ODDS';
    private const ODDS_MIN_VALUE = 1;
    private const ODDS_MAX_VALUE = 10000;
    private const PREVIOUS_ACTION = "PREVIOUS_ACTION";
    private const MAX_WIN_AMOUNT = 'MAX_WIN_AMOUNT';
    private const MAX_WIN_AMOUNT_VALUE = 20000;
    private const INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    
    /**
     * bet
     *
     * @param  mixed $data
     * @return Json  $resposnse
     */
    public function bet($data)
    {
        try {
            // Assigning inputs to variables
            $this->player_id = $data['player_id'];
            $this->stake_amount = $data['stake_amount'];
            $this->selections = $data['selections'];

            // Validate inputs with conditions
            if (is_int($this->player_id) && $this->validateDecimalInputs(self::STAKE, $this->stake_amount)) {
                $this->player = $this->getPlayer($this->player_id);
                if ($this->player == null) {
                    $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
                    return $this->failed();
                }

                // Validate selection inputs with all senarios
                foreach ($this->selections as $key_1 => $value_1) {
                    if ($this->validateDecimalInputs(self::ODDS, $value_1['odds']) && is_int($value_1['id'])) {
                        $this->odds_multiples[] = $value_1['odds'];
                        $this->odds_validation = $this->validation(self::ODDS, $value_1['odds']);
                        if ($this->odds_validation != null) {
                            $this->errors_at_select[] = $this->odds_validation;
                        }
                        foreach ($this->selections as $key_2 => $value_2) {
                            if (((int) $value_1['id'] == (int) $value_2['id']) && ($key_1 != $key_2)) {
                                $this->errors_at_select[] = $this->validation(self::DUPLICATE_SELECTIONS, null);
                            }
                        }
                        if (count($this->errors_at_select) > 0) {
                            $this->error_object['id'] = $value_1['id'];
                            $this->error_object['errors'] = $this->errors_at_select;
                            $this->selection_errors[] = $this->error_object;
                        }
                        $this->error_object = [];
                        $this->errors_at_select = [];
                    } else {
                        $this->structure_mismatch_validation = $this->validation(self::STRUCTURE_MISMATCH, null);
                        return $this->failed();
                    }
                }

                // Checking global validations
                $this->stake_validation = $this->validation(self::STAKE, $this->stake_amount);
                $this->selections_validation = $this->validation(self::SELECTIONS, count($this->selections));
                $this->max_win_amount_validation = $this->validation(self::MAX_WIN_AMOUNT, (float) $this->stake_amount * (float) array_product($this->odds_multiples));

                // Checking on players whcih is not finished previous action
                if ($this->player->busy == 1) {
                    $this->previous_action_validation = $this->validation(self::PREVIOUS_ACTION, null);
                } else {
                    if (!$this->updatePlayer($this->player_id, null, '1')) {
                        $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
                    }
                }
                // Checking on insufficient balance
                if ((float) $this->player->balance < (float) $this->stake_amount) {
                    $this->insufficient_validation = $this->validation(self::INSUFFICIENT_BALANCE, null);
                }

                // Updating global arrays
                $this->global_errors[] = $this->stake_validation;
                $this->global_errors[] = $this->selections_validation;
                $this->global_errors[] = $this->max_win_amount_validation;
                $this->global_errors[] = $this->previous_action_validation;
                $this->global_errors[] = $this->insufficient_validation;
                $this->global_errors[] = $this->unknown_error_validation;
                $this->global_errors = array_filter($this->global_errors);

                // If no errors proceeding success and updating database tables
                if (count($this->global_errors) == 0 && count($this->global_errors) == 0) {
                    if ($this->updateDatabase($this->player, $this->stake_amount, $this->selections)) {
                        if (!$this->updatePlayer($this->player_id, (float) $this->player->balance - (float) $this->stake_amount, '0')) {
                            $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
                            return $this->failed();
                        } else {
                            return $this->success();
                        }
                    } else {
                        $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
                        return $this->failed();
                    }
                } else {
                    return $this->failed();
                }

            } else {
                $this->structure_mismatch_validation = $this->validation(self::STRUCTURE_MISMATCH, null);
                return $this->failed();
            }
        } catch (\Throwable $th) {
            $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
            $this->global_errors[] = $this->unknown_error_validation;
            return $this->failed();
        }

    }

    //    
    /**
     * Common failed function
     * failed
     * @return array
     */
    private function failed()
    {
        if (!$this->updatePlayer($this->player_id, null, '0')) {
            $this->unknown_error_validation = $this->validation(self::UNKNOWN_ERROR, null);
        }
        $this->global_errors[] = $this->stake_validation;
        $this->global_errors[] = $this->selections_validation;
        $this->global_errors[] = $this->max_win_amount_validation;
        $this->global_errors[] = $this->previous_action_validation;
        $this->global_errors[] = $this->insufficient_validation;
        $this->global_errors[] = $this->structure_mismatch_validation;
        $this->global_errors[] = $this->unknown_error_validation;
        $this->global_errors = array_filter($this->global_errors);
        $this->errors['errors'] = ($this->my_array_unique(($this->global_errors)));
        $this->errors['selections'] = $this->selection_errors;
        return $this->error($this->errors);
    }

   
    /**
     * Removing duplicate objects from array(copied from internet)
     * my_array_unique
     * @param  mixed $array
     * @param  mixed $keep_key_assoc
     * @return array
     */
    private function my_array_unique($array, $keep_key_assoc = false)
    {
        $duplicate_keys = array();
        $tmp = array();
        foreach ($array as $key => $val) {
            if (is_object($val)) {
                $val = (array) $val;
            }
            if (!in_array($val, $tmp)) {
                $tmp[] = $val;
            } else {
                $duplicate_keys[] = $key;
            }
        }
        foreach ($duplicate_keys as $key) {
            unset($array[$key]);
        }
        return $keep_key_assoc ? $array : array_values($array);
    }
    
    /**
     * Create or get existing players
     * getPlayer
     * @param  mixed $id
     * @return object $player || null
     */
    private function getPlayer($id)
    {
        try {
            $player = Player::firstOrNew([
                'id' => $id,
            ], [
                'balance' => 1000,
                'busy' => 0,
            ]);
            $player->save();
            return $player;
        } catch (\Throwable $th) {
            return null;
        }
    }
    
    /**
     * updatePlayer
     *
     * @param  mixed $id
     * @param  mixed $balance
     * @param  mixed $busy
     * @return boolean
     */
    private function updatePlayer($id, $balance, $busy)
    {
        try {
            $player = Player::find($id);
            if ($balance != null) {
                $player->balance = $balance;
            }
            if ($busy != null) {
                $player->busy = $busy;
            }
            $player->save();
            return true;
        } catch (\Throwable $th) {
            return false;
        }

    }
    
    /**
     * After successfull request updating database
     * updateDatabase
     * @param  mixed $player
     * @param  mixed $stake_amount
     * @param  mixed $selections
     * @return boolean
     */
    private function updateDatabase($player, $stake_amount, $selections)
    {
        try {
            DB::transaction(function () use ($player, $stake_amount, $selections) {

                $balance_transaction = new BalanceTransaction;
                $balance_transaction->player_id = $player->id;
                $balance_transaction->amount = (float) $player->balance - (float) $stake_amount;
                $balance_transaction->amount_before = $player->balance;
                $balance_transaction->save();

                $bet = new Bet;
                $bet->stake_amount = $stake_amount;
                $bet->save();

                foreach ($selections as $key => $value) {
                    $bet_selection = new BetSelection;
                    $bet_selection->bet_id = $bet->id;
                    $bet_selection->selection_id = $value['id'];
                    $bet_selection->odds = $value['odds'];
                    $bet_selection->save();
                }
            });
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
    
    /**
     * Valdate decimals for stake and odds
     * validateDecimalInputs
     * @param  mixed $type
     * @param  mixed $data
     * @return boolean
     */
    private function validateDecimalInputs($type, $data)
    {
        if (is_numeric($data) || is_float($data)) {
            if ($type == self::STAKE) {
                if (strlen(substr(strrchr($data, "."), 1)) <= 2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                if (strlen(substr(strrchr($data, "."), 1)) <= 3) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }
    
    /**
     * All validations
     * validation
     * @param  mixed $type
     * @param  mixed $data
     * @return array
     */
    private function validation($type, $data)
    {
        switch ($type) {
            case self::UNKNOWN_ERROR:
                return ['code' => 0, 'message' => "Unknown error"];
                break;
            case self::STRUCTURE_MISMATCH:
                return ['code' => 1, 'message' => "Betslip structure mismatch"];
                break;
            case self::STAKE:
                if (self::STAKE_MIN_VALUE > $data) {
                    return ['code' => 2, 'message' => "Minimum stake amount is :" . self::STAKE_MIN_VALUE];
                }
            case self::STAKE:
                if (self::STAKE_MAX_VALUE < $data) {
                    return ['code' => 3, 'message' => "Maximum stake amount is :" . self::STAKE_MAX_VALUE];
                }
                break;
            case self::SELECTIONS:
                if (self::SELECTIONS_MIN_VALUE > $data) {
                    return ['code' => 4, 'message' => "Minimum number of selections is :" . self::SELECTIONS_MIN_VALUE];
                }
            case self::SELECTIONS:
                if (self::SELECTIONS_MAX_VALUE < $data) {
                    return ['code' => 5, 'message' => "Maximum number of selections is :" . self::SELECTIONS_MAX_VALUE];
                }
                break;
            case self::ODDS:
                if (self::ODDS_MIN_VALUE > $data) {
                    return ['code' => 6, 'message' => "Minimum odds are :" . self::ODDS_MIN_VALUE];
                }
            case self::ODDS:
                if (self::ODDS_MAX_VALUE < $data) {
                    return ['code' => 7, 'message' => "Maximum odds are :" . self::ODDS_MAX_VALUE];
                }
                break;
            case self::DUPLICATE_SELECTIONS:
                return ['code' => 8, 'message' => "Duplicate selection found"];
                break;
            case self::MAX_WIN_AMOUNT:
                if (self::MAX_WIN_AMOUNT_VALUE < $data) {
                    return ['code' => 9, 'message' => "Maximum win amount is :" . self::MAX_WIN_AMOUNT_VALUE];
                }
                break;
            case self::PREVIOUS_ACTION:
                return ['code' => 10, 'message' => "Your previous action is not finished yet"];
                break;
            case self::INSUFFICIENT_BALANCE:
                return ['code' => 11, 'message' => "Insufficient balance"];
                break;
        }
    }
}
