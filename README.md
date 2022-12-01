# SAML IDP Example

將Azure AD作為SAML SP，串接自建的IDP

## 安裝說明
- 將SAML的key與cert放到`storage/app/samlidp/cert.pem`, `storage/app/samlidp/key.pem`

## 重要檔案
- app/Http/Controllers/LoginController.php
- routes/web.php
