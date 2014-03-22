<?php

namespace Eva\EvaUser\Models;


use Eva\EvaUser\Entities;
use \Phalcon\Mvc\Model\Message as Message;
use Eva\EvaEngine\Exception;

class ResetPassword extends Entities\Users
{
    protected $resetPasswordHashExpired = 3600;

    public function requestResetPassword()
    {
        $userinfo = array();
        if($this->username) {
            $userinfo = self::findFirst("username = '$this->username'");
        } elseif($this->email) {
            $userinfo = self::findFirst("email = '$this->email'");
        }

        if(!$userinfo) {
            throw new Exception\ResourceNotFoundException('ERR_USER_NOT_EXIST');
        }

        //status tranfer only allow inactive => active
        if($userinfo->status != 'active') {
            throw new Exception\OperationNotPermitedException('ERR_USER_NOT_ACTIVED');
        }

        // generate random hash for email password reset verification (40 char string)
        $userinfo->passwordResetHash = sha1(uniqid(mt_rand(), true));
        $userinfo->passwordResetTimestamp = time();
        $userinfo->save();

        $this->sendPasswordResetMail($userinfo->email);
        return true;
    }

    public function sendPasswordResetMail($email)
    {
        $userinfo = self::findFirst("email= '$email'");
        if(!$userinfo) {
            throw new Exception\ResourceNotFoundException('ERR_USER_NOT_EXIST');
        }

        $mailer = $this->getDi()->get('mailer');
        $message = \Swift_Message::newInstance()
        ->setSubject('Reset Password')
        ->setFrom(array('noreply@wallstreetcn.com' => 'WallsteetCN'))
        ->setTo(array($userinfo->email => $userinfo->username))
        ->setBody('http://www.goldtoutiao.com/session/reset/' . urlencode($userinfo->username) . '/' . $userinfo->passwordResetHash)
        ;

        return $mailer->send($message);
    }

    /**
    * Verifies the password reset request via the verification hash token (that's only valid for one hour)
    * @param string $userName Username
    * @param string $verificationCode Hash token
    * @return bool Success status
    */
    public function verifyPasswordReset($username, $verificationCode)
    {
        $userinfo = self::findFirst("username = '$username'");
        if(!$userinfo) {
            throw new Exception\ResourceNotFoundException('ERR_USER_NOT_EXIST');
        }

        if($userinfo->status != 'active') {
            throw new Exception\OperationNotPermitedException('ERR_USER_NOT_ACTIVED');
        }

        if($userinfo->passwordResetHash != $verificationCode) {
            throw new Exception\VerifyFailedException('ERR_USER_RESET_CODE_NOT_MATCH');
        }

        if($userinfo->passwordResetTimestamp < time() - $this->resetPasswordHashExpired) {
            throw new Exception\ResourceExpiredException('ERR_USER_RESET_CODE_EXPIRED');
        }
        return true;
    }

    public function resetPassword()
    {
        if(!$this->password) {
            throw new Exception\InvalidArgumentException('ERR_USER_NO_NEW_PASSWORD_INPUT');
        }

        $userinfo = self::findFirst("username = '$this->username'");
        if(!$userinfo) {
            throw new Exception\ResourceNotFoundException('ERR_USER_NOT_EXIST');
        }

        $userinfo->password = password_hash($this->password, PASSWORD_DEFAULT, array('cost' => 10));
        $userinfo->save();
        return true;
    }
}
