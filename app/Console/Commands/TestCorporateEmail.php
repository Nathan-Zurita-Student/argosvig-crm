<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TestCorporateEmail extends Command
{
    protected $signature = 'crm:test-email {recipient : Authorized test mailbox}';

    protected $description = 'Send a synthetic message directly through the configured SMTP transport';

    public function handle(): int
    {
        $recipient = $this->argument('recipient');
        $validation = Validator::make(['recipient' => $recipient, 'sender' => config('mail.from.address')], [
            'recipient' => ['required', 'email:rfc'],
            'sender' => ['required', 'email:rfc'],
        ]);

        if ($validation->fails() || blank(config('mail.mailers.smtp.host'))) {
            $this->error('Configure o SMTP e informe um remetente e um destinatário válidos.');

            return self::FAILURE;
        }

        try {
            $result = Mail::mailer('smtp')->raw('Mensagem de teste da instalação do CRM Argosvig.', function (Message $message) use ($recipient) {
                $message->to($recipient)->subject('Teste de SMTP · Argosvig');
            });
        } catch (Throwable) {
            $this->error('O envio SMTP falhou. Confira endereço, porta, TLS e credenciais no ambiente.');

            return self::FAILURE;
        }

        if ($result === null) {
            $this->error('O transporte não confirmou o envio.');

            return self::FAILURE;
        }

        $this->info('O SMTP aceitou a mensagem de teste. Confira o recebimento na caixa autorizada.');

        return self::SUCCESS;
    }
}
