<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\LDAPContactsBackend\Service;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;
use OCA\LDAPContactsBackend\AppInfo\Application;
use OCA\LDAPContactsBackend\Exception\ConfigurationNotFound;
use OCA\LDAPContactsBackend\Model\Configuration as ConfigurationModel;
use OCP\IConfig;

class AddressBookProvider implements IAddressBookProvider {

	/** @var Configuration */
	private $configurationService;
	/** @var LdapQuerentFactory */
	private $ldapQuerentFactory;
	/** @var IConfig */
	private $config;
	/** @var ContactsAddressBookFactory */
	private $contactsAddressBookFactory;

	public function __construct(
		Configuration $configurationService,
		LdapQuerentFactory $ldapQuerentFactory,
		IConfig $config,
		ContactsAddressBookFactory $contactsAddressBookFactory
	) {
		$this->configurationService = $configurationService;
		$this->ldapQuerentFactory = $ldapQuerentFactory;
		$this->config = $config;
		$this->contactsAddressBookFactory = $contactsAddressBookFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APPID;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForAddressBookHome(string $principalUri): array {
		$configs = array_filter(
			$this->configurationService->getAll(),
			function (ConfigurationModel $config) {
				return $config->isEnabled();
			}
		);

		$addressBooks = [];
		foreach ($configs as $config) {
			/** @var ConfigurationModel $config */
			$cardBackend = new LdapCardBackend($this->ldapQuerentFactory->get($config), $config);
			$addressBooks[] = new AddressBook(Application::APPID, $cardBackend);
		}
		return $addressBooks;
	}

	/**
	 * @inheritDoc
	 */
	public function hasAddressBookInAddressBookHome(string $principalUri, string $uri): bool {
		foreach ($this->configurationService->getAll() as $config) {
			/** @var ConfigurationModel $config */
			if ($config->isEnabled()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getAddressBookInAddressBookHome(string $principalUri, string $uri): ?ExternalAddressBook {
		foreach ($this->configurationService->getAll() as $config) {
			/** @var ConfigurationModel $config */
			if ($config->isEnabled() && (string)$config->getId() === $uri) {
				$cardBackend = new LdapCardBackend($this->ldapQuerentFactory->get($config), $config);
				return new AddressBook($principalUri, $cardBackend);
			}
		}
		return null;
	}

	/**
	 * @throws ConfigurationNotFound
	 */
	public function getAddressBookById(int $addressBookId) {
		$config = $this->configurationService->get($addressBookId);
		$cardBackend = new LdapCardBackend($this->ldapQuerentFactory->get($config), $config);
		return new AddressBook(Application::APPID, $cardBackend);
	}

	/**
	 * @return ContactsAddressBook[]
	 */
	public function fetchAllForContactsStore(): array {
		$configs = array_filter(
			$this->configurationService->getAll(),
			function (ConfigurationModel $config) {
				return $config->isEnabled();
			}
		);

		$addressBooks = [];
		foreach ($configs as $config) {
			/** @var ConfigurationModel $config */
			$cardBackend = new LdapCardBackend($this->ldapQuerentFactory->get($config), $config);
			$addressBooks[] = $this->contactsAddressBookFactory->get($cardBackend);
		}
		return $addressBooks;
	}
}
