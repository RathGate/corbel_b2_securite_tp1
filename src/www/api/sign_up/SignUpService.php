<?php
namespace api\sign_up;
require_once __DIR__."/../../autoload.php";
use api\DatabaseService;
use libs\Api;
use libs\authenticator\Authenticator;
use libs\authenticator\SecuredActioner;
use libs\Format;
use libs\templator\MailTemplator;


class SignUpService extends DatabaseService
{
    // Surcharge Service.__construct() pour ajouter le traitement spécifique de la requête.
    public function __construct($allowed_verbs=["POST"])
    {
        $this->requiredParams = [
            "POST"=>["email", "password"]
        ];
        $this->optionParams = [];
        parent::__construct($allowed_verbs);
    }

    public function CheckParameters(): void
    {
        // Wrong email format
        if (!Format::IsValidEmail($this->paramValues->email)) {
            Api::WriteErrorResponse(400, "Le format de l'email spécifié est incorrect.");
        }
        // Wrong password format
        if (!Format::IsValidPassword($this->paramValues->password)) {
            Api::WriteErrorResponse(400, "Le mot de passe spécifié n'est pas assez fort (minuscule, majuscule, symbole, chiffre et > 8 caractères).");
        }
    }

    public function POST(): void
    {
        // Check if user exists :
        $user = Authenticator::GetUserByEmail($this->database, $this->paramValues->email);
        if (isset($user)) {
            // Check if awaiting verification :
            if (Authenticator::IsVerifiedUserAccount($this->database, $user["uuid"])) {
                Api::WriteErrorResponse(409, "Un compte vérifié existe déjà pour cette adresse mail.");
            } else {
                Api::WriteErrorResponse(409, "Un compte non-vérifié existe déjà pour cette adresse mail.");
            }
            return;
        }

        // Registers new user
        $user_uuid = Authenticator::RegisterUserAccount($this->database, $this->paramValues->email, $this->paramValues->password);
        // OTP
        $otp = SecuredActioner::RegisterOTP($this->database, $user_uuid, $this->serviceName);

        // Writes response
        $message = "Le compte a été crée et un email de confirmation a été envoyé à l'adresse '".$this->paramValues->email."'.";
        $mail = MailTemplator::GenerateAccountVerificationEmail($this->paramValues->email, $otp);
        Api::WriteResponse(true, 201, $mail, $message);
    }
}
