<?php 
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.bsecure.pk
 * @since      1.0.1
 *
 * @package    Bsecure_Give_Functions
 * @subpackage Bsecure_Give_Functions/includes
 */
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.1
 * @package    Bsecure_Give_Functions
 * @subpackage Bsecure_Give_Functions/includes
 * @author     bSecure <info@bsecure.pk>
 */

class Bsecure_Give_Functions extends Bsecure_Give {
    
    /*
     * Remove country code from phone number
     */
    public  function  phoneWithoutCountryCode($phone_number, $country_code='92', $country = 'PK' ){

            if (preg_match('/^\+\d+$/', $phone_number)){

                if(!empty($country_code)){

                     return str_replace('+'.$country_code, '', $phone_number);

                }

                return $phone_number;

            }      

            $phone_number = str_replace(array('+','-',' '), '', $phone_number);

            if(!empty($country)){                

                $country_code = $this->get_country_calling_code( $country );

                $country_code = !empty($country_code) ? str_replace(array('+','-',' '),'',$country_code) : $country_code;

            }

            if(strlen($phone_number) > 12){

                $calling_code = substr($phone_number,0,2);

                if ($calling_code == $country_code) {

                    //$phone_number = substr($phone_number, -10);

                    $phone_number = preg_replace("/^\+?$country_code|\|$country_code|\D/", '', ($phone_number));

                }else{

                    $phone_number = preg_replace("/^\+?$calling_code|\|$calling_code|\D/", '', ($phone_number));

                }

            }

            $hasZero = substr($phone_number, 0,1);

            if($hasZero == '0'){

                $phone_number = substr($phone_number, 1,strlen($phone_number));

            }        

            return $phone_number;

    }
    

    /*
     * Add country code in phone number
     */    
    public function  phoneWithCountryCode($phone_number, $country_code='92', $country = 'PK'){        

        if(!str_contains($phone_number,'+')){
            $phone_number = '+'.$country_code.$phone_number;
        }

        return $phone_number;

    }
    


    /* Validate at checkout page */    
    public function isPhoneNumberValid($phone_number) {

       if (!preg_match('/^\d+$/', $phone_number)){

            return false;

        }

        return true;

    }  
    
    /* Get country code form Give countries */
    public function getCountryCodeByCountryName($country_name){

        $countries = give_get_country_list();

        foreach ($countries as $key => $value) {

            if($value == $country_name)
            return $key;
        }

        return $country_name;
    }
    

    
    /* Get state code form Give states */        
    public function getStateCode($country_name, $state_name){

        $states = give_get_states( getCountryCodeByCountryName($country_name) );
        
        if(!empty($states)){

            foreach ($states as $key => $value) {

                if(str_replace([' '], '-', $value) == str_replace([' '], '-', $state_name))

                return $key;
            }
        }

        return $state_name;
    }    
  

    public function createUrlSlug($urlString)
    {
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $urlString);
        return $slug;
    }
    

}