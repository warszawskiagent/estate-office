import React, { useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, View } from 'react-native';

import { useAuthStore } from '../store/useAuthStore';
import { RootStackParamList, MainTabParamList, PropertiesStackParamList, ClientsStackParamList, AgreementsStackParamList, SearchesStackParamList } from './types';

import LoginScreen from '../screens/LoginScreen';
import DashboardScreen from '../screens/DashboardScreen';

import PropertiesScreen from '../screens/properties/PropertiesScreen';
import PropertyDetailScreen from '../screens/properties/PropertyDetailScreen';
import PropertyFormScreen from '../screens/properties/PropertyFormScreen';

import ClientsScreen from '../screens/clients/ClientsScreen';
import ClientDetailScreen from '../screens/clients/ClientDetailScreen';
import ClientFormScreen from '../screens/clients/ClientFormScreen';

import AgreementsScreen from '../screens/agreements/AgreementsScreen';
import AgreementDetailScreen from '../screens/agreements/AgreementDetailScreen';
import AgreementFormScreen from '../screens/agreements/AgreementFormScreen';

import SearchesScreen from '../screens/searches/SearchesScreen';
import SearchDetailScreen from '../screens/searches/SearchDetailScreen';
import SearchFormScreen from '../screens/searches/SearchFormScreen';

const PRIMARY = '#1a237e';

const RootStack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();
const PropertiesStack = createNativeStackNavigator<PropertiesStackParamList>();
const ClientsStack = createNativeStackNavigator<ClientsStackParamList>();
const AgreementsStack = createNativeStackNavigator<AgreementsStackParamList>();
const SearchesStack = createNativeStackNavigator<SearchesStackParamList>();

function PropertiesNavigator() {
  return (
    <PropertiesStack.Navigator screenOptions={{ headerTintColor: PRIMARY }}>
      <PropertiesStack.Screen name="PropertiesList" component={PropertiesScreen} options={{ title: 'Nieruchomości' }} />
      <PropertiesStack.Screen name="PropertyDetail" component={PropertyDetailScreen} options={{ title: 'Nieruchomość' }} />
      <PropertiesStack.Screen name="PropertyForm" component={PropertyFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Edytuj nieruchomość' : 'Nowa nieruchomość' })} />
    </PropertiesStack.Navigator>
  );
}

function ClientsNavigator() {
  return (
    <ClientsStack.Navigator screenOptions={{ headerTintColor: PRIMARY }}>
      <ClientsStack.Screen name="ClientsList" component={ClientsScreen} options={{ title: 'Klienci' }} />
      <ClientsStack.Screen name="ClientDetail" component={ClientDetailScreen} options={{ title: 'Klient' }} />
      <ClientsStack.Screen name="ClientForm" component={ClientFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Edytuj klienta' : 'Nowy klient' })} />
    </ClientsStack.Navigator>
  );
}

function AgreementsNavigator() {
  return (
    <AgreementsStack.Navigator screenOptions={{ headerTintColor: PRIMARY }}>
      <AgreementsStack.Screen name="AgreementsList" component={AgreementsScreen} options={{ title: 'Umowy' }} />
      <AgreementsStack.Screen name="AgreementDetail" component={AgreementDetailScreen} options={{ title: 'Umowa' }} />
      <AgreementsStack.Screen name="AgreementForm" component={AgreementFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Edytuj umowę' : 'Nowa umowa' })} />
    </AgreementsStack.Navigator>
  );
}

function SearchesNavigator() {
  return (
    <SearchesStack.Navigator screenOptions={{ headerTintColor: PRIMARY }}>
      <SearchesStack.Screen name="SearchesList" component={SearchesScreen} options={{ title: 'Poszukiwania' }} />
      <SearchesStack.Screen name="SearchDetail" component={SearchDetailScreen} options={{ title: 'Poszukiwanie' }} />
      <SearchesStack.Screen name="SearchForm" component={SearchFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Edytuj poszukiwanie' : 'Nowe poszukiwanie' })} />
    </SearchesStack.Navigator>
  );
}

function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarActiveTintColor: PRIMARY,
        tabBarInactiveTintColor: '#757575',
        headerShown: false,
        tabBarIcon: ({ color, size }) => {
          const icons: Record<string, keyof typeof Ionicons.glyphMap> = {
            Dashboard: 'grid-outline',
            PropertiesStack: 'home-outline',
            ClientsStack: 'people-outline',
            AgreementsStack: 'document-text-outline',
            SearchesStack: 'search-outline',
          };
          return <Ionicons name={icons[route.name] ?? 'ellipse-outline'} size={size} color={color} />;
        },
        tabBarLabel: {
          Dashboard: 'Pulpit',
          PropertiesStack: 'Oferty',
          ClientsStack: 'Klienci',
          AgreementsStack: 'Umowy',
          SearchesStack: 'Poszukiwania',
        }[route.name] ?? route.name,
      })}
    >
      <Tab.Screen name="Dashboard" component={DashboardScreen} options={{ title: 'Pulpit', headerShown: true, headerTintColor: PRIMARY }} />
      <Tab.Screen name="PropertiesStack" component={PropertiesNavigator} />
      <Tab.Screen name="ClientsStack" component={ClientsNavigator} />
      <Tab.Screen name="AgreementsStack" component={AgreementsNavigator} />
      <Tab.Screen name="SearchesStack" component={SearchesNavigator} />
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  const { user, isRestoringSession, restoreSession } = useAuthStore();

  useEffect(() => {
    restoreSession();
  }, []);

  if (isRestoringSession) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={PRIMARY} />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <RootStack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <RootStack.Screen name="Main" component={MainTabs} />
        ) : (
          <RootStack.Screen name="Login" component={LoginScreen} />
        )}
      </RootStack.Navigator>
    </NavigationContainer>
  );
}
