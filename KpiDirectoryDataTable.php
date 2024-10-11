<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\UrlGenerator;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use App\Models\Site\Division;
use App\Models\Site\District;
use App\Models\Site\Tehsil;
use App\Models\Site\Center;
use App\Models\Site\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Site\UserRoles;
use App\Models\Site\Roles;
use Hash;
use App\Services\UserService;
use App\Models\Site\FlourMill;
use App\Models\Site\KpiDirectory\KpiEmployeeDirectory;
use App\Models\Site\KpiDirectory\KpiOfficer;
use App\Models\Site\KpiDirectory\KpiEmployeeEvaluation;

use App\Models\Site\FoodLims\LabUserDivision;

use Illuminate\Support\Facades\Input;
use Auth;
use Illuminate\Support\Facades\Response;
use DB;
use Session;
use Exception;

class KpiDirectoryDataTable 
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public static function dataTable($request){
        if(!hasPrivilege('kpi_directory_listing','can_view')){
            throw new Exception('You do not have rights to view this page!');
        }
        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            throw new Exception('No chapter found!');
        }

         
        $division_id = $request->division_id;  
        $district_id = $request->district_id;
        $tehsil_id = $request->tehsil_id;
        $ked_grade = $request->ked_grade;
        $array_string = array();

        $user_role = Session::get('user_info.user_role'); //$user->getUserRole->role->name;
        
        $user_division = Session::get('user_info.division_id');  
        $user_district = Session::get('user_info.district_id'); 
        $user_tehsil = Session::get('user_info.tehsil_id');  
       	$user_id = Auth::id();
        $user_role_id  = UserRoles::where('user_id',Auth::id())->first();  
        $kpiOfficer = KpiOfficer::select('kpio_id')->where('reporting_officer',$user_role_id->role_id)->get(); 
        $arr = array();
        foreach($kpiOfficer as $kpiOfficers){
            $arr[] =  $kpiOfficers->kpio_id;
        } 
        $str = implode (", ", $arr);
        $query = KpiEmployeeDirectory::Active()
            ->select('kpi_employee_directory.*','kpi_grade.kg_grade_name as grade_name','kg_id','domicile.district_name as domicile_name','division_idFk','divisions.name as division_name','districts.district_name','tehsils.tehsil_name')
            ->leftJoin('kpi_grade', 'kpi_employee_directory.ked_grade_idFk', '=', 'kg_id')
            ->leftJoin('districts as domicile', 'kpi_employee_directory.ked_domicile_idFk', '=', 'domicile.district_id') 
            ->leftJoin('divisions', 'kpi_employee_directory.division_idFk', '=', 'division_id')
            ->leftJoin('districts', 'kpi_employee_directory.district_idFk', '=', 'districts.district_id') 
            ->leftJoin('tehsils', 'kpi_employee_directory.tehsil_idFk', '=', 'tehsil_id'); 
        $query = $query->whereIn('ked_officer_idFk', $arr);     

        if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner','SRO','RO'))){
            $division = ($user_division ); 
            $query = $query->where('kpi_employee_directory.division_idFk', '=', $division);
        }
        if ($division_id != '' && $division_id >0) {
            $query = $query->where('kpi_employee_directory.division_idFk', $division_id);
        }
        if(in_array($user_role, array('District User', 'DC User'))){
            
            $district = ($user_district == '' ? $district_id : $user_district);  
            $query = $query->where('kpi_employee_directory.district_idFk','=',$district);
        }
        if ($district_id != '' && $district_id > 0) {
            $query = $query->where('kpi_employee_directory.district_idFk', $district_id);
        }
        if(in_array($user_role, array('Tehsil User','PLRA User')) || ($tehsil_id != '' && $tehsil_id > 0)){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            $query = $query->where('kpi_employee_directory.tehsil_idFk','=',$tehsil); 
        }
        if ($ked_grade !='') {
            $query = $query->where('kpi_employee_directory.ked_grade_idFk','=',$ked_grade); 
        } 
         
        $data =  Datatables::of($query)

                ->setRowId(function ($query) {
                    return 'farmer_'.$query->ked_id;
                })
                ->addColumn('ked_name',function($query){
                    return $query->ked_name;
                })
                ->addColumn('ked_father_name',function($query){
                    return $query->ked_father_name;
                })
                ->addColumn('ked_cnic',function($query){
                    return $query->ked_cnic;
                })
                ->addColumn('ked_dob',function($query){
                    return $query->ked_dob;
                })
                ->addColumn('grade_name',function($query){
                    return $query->grade_name;
                })
                ->addColumn('domicile_name',function($query){
                    return $query->domicile_name;
                })
                ->addColumn('ked_start_date_gov',function($query){
                    return $query->ked_start_date_gov;
                })
                ->addColumn('divisions.division_name',function($query){
                    return $query->division_name;
                })
                ->editColumn('districts.district_name',function($query){
                    return $query->district_name;
                })
                ->editColumn('tehsils.tehsil_name',function($query){
                    return $query->tehsil_name;
                })
                ->editColumn('kpi_employee_directory.created_at',function($query){
                    return  date('d-m-Y', strtotime($query->created_at));
                });
                if(hasPrivilege('kpi_directory_listing','can_add') ){
                    
                    $data->addColumn('manage',function($query){
                        $manage = "";
                        if(hasPrivilege('kpi_directory_listing','can_view')){
                            if($query->ked_grade_idFk >= 1 &&  $query->ked_grade_idFk <=15) {
                                $manage .= '<a href="'.route('pinkForm',[$query->ked_id]).'" class="" title="Print"><i class="fas fa-print">Pink Form</i></a>';
                            }
                            else{
                                $manage .= '<a href="'.route('parrotForm',[$query->ked_id]).'" class="" title="Print"><i class="fas fa-print">Parrot form</i></a>';
                            }
                        }
                        return $manage;
                    });
                    $data->rawColumns(['manage']);
                }
                
                

            return $data->addIndexColumn()->make(true);
    }

    public static function evaluationDataTable($request){
        if(!hasPrivilege('kpi_directory_listing','can_view')){
            throw new Exception('You do not have rights to view this page!');
        }
        $chapter_dates = get_chapter_dates($request->segment(1));
        if(!$chapter_dates){
            throw new Exception('No chapter found!');
        }

         
        $division_id = $request->division_id;  
        $district_id = $request->district_id;
        $tehsil_id = $request->tehsil_id;
         
  

        $user_role = Session::get('user_info.user_role'); //$user->getUserRole->role->name;
        
        $user_division = Session::get('user_info.division_id');  
        $user_district = Session::get('user_info.district_id'); 
        $user_tehsil = Session::get('user_info.tehsil_id');  
        $user_id = Auth::id();
        $user_role_id  = UserRoles::where('user_id',Auth::id())->first();  
        $kpiOfficer = KpiOfficer::select('*')->where('reporting_officer',$user_role_id->role_id)->get();  
        $kpi_countersigning_officer = KpiOfficer::select('*')->where('countersigning_officer',$user_role_id->role_id)->get();  
         $arr = array();
        $counter_signing_arr = array(); 
        foreach($kpiOfficer as $kpiOfficers){
            $arr[] =  $kpiOfficers->kpio_id;
        }  
        foreach($kpi_countersigning_officer as $kpi_countersigning_officers){
            $counter_signing_arr[] =  $kpi_countersigning_officers->kpio_id;
        } 
         
        
            
        $query = KpiEmployeeEvaluation::Active()
            ->select('kpi_employee_evaluation.*','kpi_employee_directory.*','kpi_grade.kg_grade_name as grade_name','domicile.district_name as domicile_name','division_idFk','divisions.name as division_name','districts.district_name','tehsils.tehsil_name')
            ->leftjoin('kpi_employee_directory','employee_idFk','ked_id')
            ->leftJoin('kpi_grade', 'kpi_employee_directory.ked_grade_idFk', '=', 'kg_id')
            ->leftJoin('districts as domicile', 'kpi_employee_directory.ked_domicile_idFk', '=', 'domicile.district_id') 
            ->leftJoin('divisions', 'kpi_employee_directory.division_idFk', '=', 'division_id')
            ->leftJoin('districts', 'kpi_employee_directory.district_idFk', '=', 'districts.district_id') 
            ->leftJoin('tehsils', 'kpi_employee_directory.tehsil_idFk', '=', 'tehsil_id');
        $query = $query->where('kpi_employee_evaluation.kee_reporting_status',1);
            if($counter_signing_arr){
                $query = $query->whereIn('ked_officer_idFk', $counter_signing_arr); 
            }else{
                $query = $query->whereIn('ked_officer_idFk', $arr);
            }
            if(in_array($user_role, array('Division User','Deputy Director', 'Sugar Commissioner','SRO','RO'))){
                $division = ($user_division );   
                
                $query = $query->where('kpi_employee_directory.division_idFk', '=', $division);
            }
            if ($division_id != '' && $division_id >0) {
                $query = $query->where('kpi_employee_directory.division_idFk', $division_id);
            }
            if(in_array($user_role, array('District User', 'DC User')) || ($district_id != '' && $district_id > 0)){
                
                $district = ($user_district == '' ? $district_id : $user_district); 
                $query = $query->where('kpi_employee_directory.district_idFk','=',$district);
            }
        if(in_array($user_role, array('Tehsil User','PLRA User')) || ($tehsil_id != '' && $tehsil_id > 0)){
            $tehsil = ($user_tehsil == '' ? $tehsil_id : $user_tehsil);
            $query = $query->where('kpi_employee_directory.tehsil_idFk','=',$tehsil); 
        }
         
         
        $data =  Datatables::of($query)

                ->setRowId(function ($query) {
                    return 'farmer_'.$query->ked_id;
                })
                ->addColumn('ked_name',function($query){
                    return $query->ked_name;
                })
                ->addColumn('ked_father_name',function($query){
                    return $query->ked_father_name;
                })
                ->addColumn('ked_cnic',function($query){
                    return $query->ked_cnic;
                })
                ->addColumn('ked_dob',function($query){
                    return $query->ked_dob;
                })
                ->addColumn('grade_name',function($query){
                    return $query->grade_name;
                })
                ->addColumn('domicile_name',function($query){
                    return $query->domicile_name;
                })
                ->addColumn('ked_start_date_gov',function($query){
                    return $query->ked_start_date_gov;
                })
                ->addColumn('divisions.division_name',function($query){
                    return $query->division_name;
                })
                ->editColumn('districts.district_name',function($query){
                    return $query->district_name;
                })
                ->editColumn('tehsils.tehsil_name',function($query){
                    return $query->tehsil_name;
                })
                ->editColumn('kpi_employee_evaluation.kee_acadmic_qualification',function($query){
                    return $query->kee_acadmic_qualification;
                })
                ->editColumn('kpi_employee_evaluation.kee_technical_qualification',function($query){
                    return $query->kee_technical_qualification;
                })
                ->editColumn('kpi_employee_directory.ked_training_received',function($query){
                    return $query->ked_training_received;
                })
                ->editColumn('kpi_employee_directory.created_at',function($query){
                    return  date('d-m-Y', strtotime($query->created_at));
                });
                // if(hasPrivilege('kpi_directory_listing','can_add') ){
                    
                //     $data->addColumn('manage',function($query){
                //         $manage = "";
                //         // if(hasPrivilege('kpi_directory_listing','can_view')){
                //             if($query->ked_grade_idFk >= 1 &&  $query->ked_grade_idFk <=15) {
                //                 $manage .= '<a href="'.route('pinkForm',[$query->ked_id]).'" class="" title="Print"><i class="fas fa-print">Pink Form</i></a>';
                //             }
                //             else{
                //                 $manage .= '<a href="'.route('editWheatTransfer',[$query->ked_id]).'" class="" title="Print"><i class="fas fa-print">Parrot form</i></a>';
                //             }
                //         // }
                //         return $manage;
                //     });
                    // $data->rawColumns(['manage']);
                // }

            return $data->addIndexColumn()->make(true);
    }
}