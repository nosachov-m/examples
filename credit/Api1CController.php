<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\CreditsLog;
use App\Cashbox;
use App\Document;
use App\CreditRequest;

class Api1CController extends Controller
{

    CONST ACCOUNT_PLATON = [
        'title' => 'Распределительный счет по кредитам и займам «ПЛАТОН»',
        'account' => 333
    ];

    CONST ACCOUNT_TITLE = 'Статья движения денежных средств';

    CONST FINANCIAL_ACCOUNTS = [
        -3771 => [
            'DT' => 3771,
            'KT' => 333,
            'title' => 'Выдан кредит ',
            'type' => 'IN',
            'action' => 'видача позики',
            'fin_text' => 'по Дебету',
        ],
        3771 => [
            'DT' => 333,
            'KT' => 3771,
            'title' => 'Погашение кредита ',
            'type' => 'OUT',
            'action' => 'погашення позики',
            'fin_text' => 'по Кредиту',
        ],
        311 => [
            'DT' => 333,
            'KT' => 3771,
            'title' => 'Погашение кредита ',
            'type' => 'OUT',
            'action' => 'погашення позики',
            'fin_text' => 'по Кредиту',
        ],
        3731 => [
            'DT' => 333,
            'KT' => '373.1',
            'title' => '',
            'type' => 'IN',
            'action' => 'погашення %',
            'fin_text' => 'по Кредиту',
        ],
        3732 => [
            'DT' => 333,
            'KT' => '373.2',
            'title' => '',
            'type' => 'IN',
            'action' => 'погашення % пролонг',
            'fin_text' => 'по Кредиту',
        ],
        3741 => [
            'DT' => 333,
            'KT' => '374.1',
            'title' => '',
            'type' => 'IN',
            'action' => 'погашення пени/штраф',
            'fin_text' => 'по Кредиту',
        ],
        374 => [
            'DT' => 333,
            'KT' => '374.1',
            'title' => '',
            'type' => 'IN',
            'action' => 'погашення пени/штраф',
            'fin_text' => 'по Кредиту',
        ]

    ];

    public function getReport(Request $request)
    {
        if(!$request->has('period')){
            return response('You must pass the "period" parameter in format Y-m', 404);
        }
        $period = $request->period;

        try{
            $start_date = Carbon::createFromFormat('Y-m-d', $period.'-01');
        }
        catch(\Exception $e){
            return response('You must pass the "period" parameter in format Y-m', 404);
        }

        $end_date = $start_date->copy()->addMonth();

        $credits = CreditsLog::with('credit')
            ->where('author_id', '=', CreditsLog::LOG_AUTHOR_CRON)
            ->where('date', '>=', $start_date->format('Y-m-d'))
            ->where('date', '<', $end_date->format('Y-m-d'))
            ->orderBy('credit_id')
            ->get()
            ->groupBy('credit_id');

        $report = [];

        foreach($credits as $credit){
            $get_user_data = false;
            $single = [];
            $diff = [];

            foreach($credit as $credit_day){

                if(!$get_user_data){
                    $get_user_data = $credit_day['credit']->user()->first();
                    $single['credit_id'] = $credit_day->credit_id;
                    $single['inn'] = $get_user_data->inn;
                    $single['first_name'] = $get_user_data->firstname;
                    $single['last_name'] = $get_user_data->lastname;
                    $single['start_date'] = $credit_day['credit']->start_date;

                }

                $diff[] = [
                    'date' => $credit_day->date,
                    'amount_paid' => $credit_day->amount_paid,
                    'percent' => $credit_day->percent,
                    'percent_paid' => $credit_day->percent_paid,
                    'penalty' => $credit_day->penalty,
                    'penalty_paid' => $credit_day->penalty_paid
                ];

            }

            $single['diff']= $diff;
            $report[] = $single;

        }
        return response()->json($report, 200);
    }

    public function get1CDocuments(Request $request)
    {
        if(!$request->has('period')){
            return response('You must pass the "period" parameter in format Y-m-d', 404);
        }
        $period = $request->period;

        try{
            $start_date = Carbon::createFromFormat('Y-m-d h:i:s', $period.' 00:00:00');
        }
        catch(\Exception $e){
            return response('You must pass the "period" parameter in format Y-m-d', 404);
        }

        $end_date = $start_date->copy()->addDay();
        $cashbox = Cashbox::with('credit', 'contract')
            ->where('created_at','>=', $start_date)
            ->where('created_at', '<', $end_date)
            ->get();
        $documents = [];

        foreach ($cashbox as $cash) {
            $doc = [];
            $account = $cash->amount < 0 ? -3771 : $cash->account;
            $client_type = $cash->amount < 0 ? 'Получатель' : 'Плательщик';
            $fio = $cash->credit->user['lastname']
                .' '.mb_substr($cash->credit->user['firstname'], 0, 1, 'utf-8')
                .'. '.mb_substr($cash->credit->user['middlename'], 0, 1, 'utf-8');
            $doc['title'] = self::FINANCIAL_ACCOUNTS[$account]['title'] ?
                self::FINANCIAL_ACCOUNTS[$account]['title']
                .' - '.$fio : '';
            $doc['account_platon'] = self::ACCOUNT_PLATON['account'].' '.self::ACCOUNT_PLATON['title'].' '.self::FINANCIAL_ACCOUNTS[$account]['fin_text'];
            $doc['client'] = $client_type.': '.$fio;
            $doc['amount'] = abs($cash->amount);
            $contract_number = Document::where('credit_id', '=', $cash->credit_id)
                ->where('type', '=', Document::TYPE_CONTRACT)
                ->first();
            if (!$contract_number) {
                $cr = CreditRequest::where('credit_id', '=', $cash->credit_id)->first();
                $contract_number = Document::where('creditRequest_id', '=', $cr->id)
                    ->where('type', '=', Document::TYPE_CONTRACT)
                    ->first();
            }

            $c_date = Carbon::createFromFormat('Y-m-d H:i:s', $contract_number->updated_at)->format('d.m.Y');
            $doc['contract'] = $contract_number->contract_number ? 'Договор: №'.$contract_number->contract_number.' от '.$c_date
                : 'Договор: №'.explode('№', $contract_number->doc_name)[1].' от '.$c_date;
            $doc['fin_text'] = $account != 311 ? $account.' '.self::FINANCIAL_ACCOUNTS[$account]['fin_text'] : self::FINANCIAL_ACCOUNTS[$account]['KT'].' '.self::FINANCIAL_ACCOUNTS[$account]['fin_text'];
            $doc['fin_title'] = self::ACCOUNT_TITLE.': '.self::FINANCIAL_ACCOUNTS[$account]['action'];
            $doc['transaction'] = 'ДТ '.self::FINANCIAL_ACCOUNTS[$account]['DT'].' КТ '.self::FINANCIAL_ACCOUNTS[$account]['KT'].' '.$cash->amount;
            $doc['id'] = $cash->id;
            $doc['created_at'] = $cash->created_at;

            $documents[] = $doc;

        }

        return response()->json($documents, 200);
    }

}