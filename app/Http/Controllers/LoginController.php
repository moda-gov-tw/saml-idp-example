<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\Response;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\Protocol\Status;
use LightSaml\SamlConstants;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Assertion\SubjectConfirmation;
use LightSaml\Model\Assertion\SubjectConfirmationData;
use LightSaml\Model\Assertion\AuthnStatement;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        return view('login', compact('request'));
    }

    public function dologin(Request $request)
    {
        $bindingFactory = new BindingFactory();
        $binding = $bindingFactory->getBindingByRequest($request);

        // We prepare a message context to receive our SAML Request message.
        $messageContext = new MessageContext();

        // The receive method fills in the messageContext with the SAML Request data.
        /** @var Response $response */
        $binding->receive($request, $messageContext);

        $saml_request = $messageContext;


        $issuer = $saml_request->getMessage()->getIssuer()->getValue();
        $id = $saml_request->getMessage()->getID();


        $user_id = "meow1234";
        $user_email = $request->username;




        $acsUrl = "https://login.microsoftonline.com/login.srf";

        // Preparing the response XML
        $serializationContext = new SerializationContext();

        // We now start constructing the SAML Response using LightSAML.
        $response = new Response();
        $response
            ->addAssertion($assertion = new Assertion())
            ->setStatus(
                new Status(
                    new StatusCode(
                        SamlConstants::STATUS_SUCCESS
                    )
                )
            )
            ->setID(\LightSaml\Helper::generateID())
            ->setIssueInstant(new \DateTime())
            ->setDestination($acsUrl)
                // We obtain the Entity ID from the Idp.
            ->setIssuer(new Issuer("https://twfido.pdis.dev/"))
            ;

        $assertion
            ->setId(\LightSaml\Helper::generateID())
            ->setIssueInstant(new \DateTime())
                // We obtain the Entity ID from the Idp.
            ->setIssuer(new Issuer("https://twfido.pdis.dev/"))
            ->setSubject(
                    (new Subject())
                        // Here we set the NameID that identifies the name of the user.
                    ->setNameID(
                        new \LightSaml\Model\Assertion\NameID(
                            $user_id,
                            SamlConstants::NAME_ID_FORMAT_UNSPECIFIED
                        )
                    )
                    ->addSubjectConfirmation(
                            (new SubjectConfirmation())
                            ->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER)
                            ->setSubjectConfirmationData(
                                    (new SubjectConfirmationData())
                                        // We set the ResponseTo to be the id of the SAMLRequest.
                                    ->setInResponseTo($id)
                                    ->setNotOnOrAfter(new \DateTime('+1 MINUTE'))
                                        // The recipient is set to the Service Provider ACS.
                                    ->setRecipient($acsUrl)
                            )
                    )
            )
            ->setConditions(
                    (new \LightSaml\Model\Assertion\Conditions())
                    ->setNotBefore(new \DateTime())
                    ->setNotOnOrAfter(new \DateTime('+1 MINUTE'))
                    ->addItem(
                            // Use the Service Provider Entity ID as AudienceRestriction.
                        new \LightSaml\Model\Assertion\AudienceRestriction([$issuer])
                    )
            )
            ->addItem(
                    (new \LightSaml\Model\Assertion\AttributeStatement())
                    ->addAttribute(
                        new \LightSaml\Model\Assertion\Attribute(
                            \LightSaml\ClaimTypes::EMAIL_ADDRESS,
                            // Setting the user email address.
                            $user_email
                        )
                    )
            )
            ->addItem(
                    (new AuthnStatement())
                    ->setAuthnInstant(new \DateTime('-10 MINUTE'))
                    ->setSessionIndex($assertion->getId())
                    ->setAuthnContext(
                            (new \LightSaml\Model\Assertion\AuthnContext())
                            ->setAuthnContextClassRef(SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT)
                    )
            )
            ;

        $cert = new \LightSaml\Credential\X509Certificate();
        $cert->loadPem(Storage::get('samlidp\cert.pem'));

        //$cert = new  \LightSaml\Credential\X509Certificate(Storage::get('samlidp\cert.pem'));
        $key = new \RobRichards\XMLSecLibs\XMLSecurityKey(\RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey(Storage::get('samlidp\key.pem'));


        // Sign the response.
        $response->setSignature(new \LightSaml\Model\XmlDSig\SignatureWriter($cert, $key));

        // Serialize to XML.
        $response->serialize($serializationContext->getDocument(), $serializationContext);

        // Set the postback url obtained from the trusted SPs as the destination.
        $response->setDestination($acsUrl);




        // dd($serializationContext->getDocument()->saveXML());

        $bindingFactory = new BindingFactory();
        $postBinding = $bindingFactory->create(SamlConstants::BINDING_SAML2_HTTP_POST);
        $messageContext = new MessageContext();
        $messageContext->setMessage($response);

        // Ensure we include the RelayState.
        $message = $messageContext->getMessage();
        $message->setRelayState($request->get('RelayState'));
        $messageContext->setMessage($message);
        // Return the Response.
        /** @var \Symfony\Component\HttpFoundation\Response $httpResponse */
        $httpResponse = $postBinding->send($messageContext);
        // dd( $httpResponse->getContent());

        return $httpResponse->getContent();
    }

}